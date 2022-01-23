<?php

namespace React\Tests\Http;

use Clue\React\Block;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\Browser;
use React\Http\HttpServer;
use React\Http\Message\ResponseException;
use React\Http\Middleware\StreamingRequestMiddleware;
use React\Http\Message\Response;
use React\Promise\Promise;
use React\Promise\Stream;
use React\Socket\Connector;
use React\Socket\SocketServer;
use React\Stream\ReadableStreamInterface;
use React\Stream\ThroughStream;
use RingCentral\Psr7\Request;

class FunctionalBrowserTest extends TestCase
{
    private $browser;
    private $base;

    /**
     * @before
     */
    public function setUpBrowserAndServer()
    {
        $this->browser = new Browser();

        $http = new HttpServer(new StreamingRequestMiddleware(), function (ServerRequestInterface $request) {
            $path = $request->getUri()->getPath();

            $headers = array();
            foreach ($request->getHeaders() as $name => $values) {
                $headers[$name] = implode(', ', $values);
            }

            if ($path === '/get') {
                return new Response(
                    200,
                    array(),
                    'hello'
                );
            }

            if ($path === '/redirect-to') {
                $params = $request->getQueryParams();
                return new Response(
                    302,
                    array('Location' => $params['url'])
                );
            }

            if ($path === '/basic-auth/user/pass') {
                return new Response(
                    $request->getHeaderLine('Authorization') === 'Basic dXNlcjpwYXNz' ? 200 : 401,
                    array(),
                    ''
                );
            }

            if ($path === '/status/204') {
                return new Response(
                    204,
                    array(),
                    ''
                );
            }

            if ($path === '/status/304') {
                return new Response(
                    304,
                    array(),
                    'Not modified'
                );
            }

            if ($path === '/status/404') {
                return new Response(
                    404,
                    array(),
                    ''
                );
            }

            if ($path === '/delay/10') {
                return new Promise(function ($resolve) {
                    Loop::addTimer(10, function () use ($resolve) {
                        $resolve(new Response(
                            200,
                            array(),
                            'hello'
                        ));
                    });
                });
            }

            if ($path === '/post') {
                return new Promise(function ($resolve) use ($request, $headers) {
                    $body = $request->getBody();
                    assert($body instanceof ReadableStreamInterface);

                    $buffer = '';
                    $body->on('data', function ($data) use (&$buffer) {
                        $buffer .= $data;
                    });

                    $body->on('close', function () use (&$buffer, $resolve, $headers) {
                        $resolve(new Response(
                            200,
                            array(),
                            json_encode(array(
                                'data' => $buffer,
                                'headers' => $headers
                            ))
                        ));
                    });
                });
            }

            if ($path === '/stream/1') {
                $stream = new ThroughStream();

                Loop::futureTick(function () use ($stream, $headers) {
                    $stream->end(json_encode(array(
                        'headers' => $headers
                    )));
                });

                return new Response(
                    200,
                    array(),
                    $stream
                );
            }

            var_dump($path);
        });
        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testSimpleRequest()
    {
        Block\await($this->browser->get($this->base . 'get'));
    }

    public function testGetRequestWithRelativeAddressRejects()
    {
        $promise = $this->browser->get('delay');

        $this->setExpectedException('InvalidArgumentException', 'Invalid request URL given');
        Block\await($promise);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testGetRequestWithBaseAndRelativeAddressResolves()
    {
        Block\await($this->browser->withBase($this->base)->get('get'));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testGetRequestWithBaseAndFullAddressResolves()
    {
        Block\await($this->browser->withBase('http://example.com/')->get($this->base . 'get'));
    }

    public function testCancelGetRequestWillRejectRequest()
    {
        $promise = $this->browser->get($this->base . 'get');
        $promise->cancel();

        $this->setExpectedException('RuntimeException');
        Block\await($promise);
    }

    public function testCancelRequestWithPromiseFollowerWillRejectRequest()
    {
        $promise = $this->browser->request('GET', $this->base . 'get')->then(function () {
            var_dump('noop');
        });
        $promise->cancel();

        $this->setExpectedException('RuntimeException');
        Block\await($promise);
    }

    public function testRequestWithoutAuthenticationFails()
    {
        $this->setExpectedException('RuntimeException');
        Block\await($this->browser->get($this->base . 'basic-auth/user/pass'));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRequestWithAuthenticationSucceeds()
    {
        $base = str_replace('://', '://user:pass@', $this->base);

        Block\await($this->browser->get($base . 'basic-auth/user/pass'));
    }

    /**
     * ```bash
     * $ curl -vL "http://httpbin.org/redirect-to?url=http://user:pass@httpbin.org/basic-auth/user/pass"
     * ```
     *
     * @doesNotPerformAssertions
     */
    public function testRedirectToPageWithAuthenticationSendsAuthenticationFromLocationHeader()
    {
        $target = str_replace('://', '://user:pass@', $this->base) . 'basic-auth/user/pass';

        Block\await($this->browser->get($this->base . 'redirect-to?url=' . urlencode($target)));
    }

    /**
     * ```bash
     * $ curl -vL "http://unknown:invalid@httpbin.org/redirect-to?url=http://user:pass@httpbin.org/basic-auth/user/pass"
     * ```
     *
     * @doesNotPerformAssertions
     */
    public function testRedirectFromPageWithInvalidAuthToPageWithCorrectAuthenticationSucceeds()
    {
        $base = str_replace('://', '://unknown:invalid@', $this->base);
        $target = str_replace('://', '://user:pass@', $this->base) . 'basic-auth/user/pass';

        Block\await($this->browser->get($base . 'redirect-to?url=' . urlencode($target)));
    }

    public function testCancelRedirectedRequestShouldReject()
    {
        $promise = $this->browser->get($this->base . 'redirect-to?url=delay%2F10');

        Loop::addTimer(0.1, function () use ($promise) {
            $promise->cancel();
        });

        $this->setExpectedException('RuntimeException', 'Request cancelled');
        Block\await($promise);
    }

    public function testTimeoutDelayedResponseShouldReject()
    {
        $promise = $this->browser->withTimeout(0.1)->get($this->base . 'delay/10');

        $this->setExpectedException('RuntimeException', 'Request timed out after 0.1 seconds');
        Block\await($promise);
    }

    public function testTimeoutDelayedResponseAfterStreamingRequestShouldReject()
    {
        $stream = new ThroughStream();
        $promise = $this->browser->withTimeout(0.1)->post($this->base . 'delay/10', array(), $stream);
        $stream->end();

        $this->setExpectedException('RuntimeException', 'Request timed out after 0.1 seconds');
        Block\await($promise);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testTimeoutFalseShouldResolveSuccessfully()
    {
        Block\await($this->browser->withTimeout(false)->get($this->base . 'get'));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRedirectRequestRelative()
    {
        Block\await($this->browser->get($this->base . 'redirect-to?url=get'));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRedirectRequestAbsolute()
    {
        Block\await($this->browser->get($this->base . 'redirect-to?url=' . urlencode($this->base . 'get')));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFollowingRedirectsFalseResolvesWithRedirectResult()
    {
        $browser = $this->browser->withFollowRedirects(false);

        Block\await($browser->get($this->base . 'redirect-to?url=get'));
    }

    public function testFollowRedirectsZeroRejectsOnRedirect()
    {
        $browser = $this->browser->withFollowRedirects(0);

        $this->setExpectedException('RuntimeException');
        Block\await($browser->get($this->base . 'redirect-to?url=get'));
    }

    public function testResponseStatus204ShouldResolveWithEmptyBody()
    {
        $response = Block\await($this->browser->get($this->base . 'status/204'));
        $this->assertFalse($response->hasHeader('Content-Length'));

        $body = $response->getBody();
        $this->assertEquals(0, $body->getSize());
        $this->assertEquals('', (string) $body);
    }

    public function testResponseStatus304ShouldResolveWithEmptyBodyButContentLengthResponseHeader()
    {
        $response = Block\await($this->browser->get($this->base . 'status/304'));
        $this->assertEquals('12', $response->getHeaderLine('Content-Length'));

        $body = $response->getBody();
        $this->assertEquals(0, $body->getSize());
        $this->assertEquals('', (string) $body);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testGetRequestWithResponseBufferMatchedExactlyResolves()
    {
        $promise = $this->browser->withResponseBuffer(5)->get($this->base . 'get');

        Block\await($promise);
    }

    public function testGetRequestWithResponseBufferExceededRejects()
    {
        $promise = $this->browser->withResponseBuffer(4)->get($this->base . 'get');

        $this->setExpectedException(
            'OverflowException',
            'Response body size of 5 bytes exceeds maximum of 4 bytes',
            defined('SOCKET_EMSGSIZE') ? SOCKET_EMSGSIZE : 0
        );
        Block\await($promise);
    }

    public function testGetRequestWithResponseBufferExceededDuringStreamingRejects()
    {
        $promise = $this->browser->withResponseBuffer(4)->get($this->base . 'stream/1');

        $this->setExpectedException(
            'OverflowException',
            'Response body size exceeds maximum of 4 bytes',
            defined('SOCKET_EMSGSIZE') ? SOCKET_EMSGSIZE : 0
        );
        Block\await($promise);
    }

    /**
     * @group internet
     * @doesNotPerformAssertions
     */
    public function testCanAccessHttps()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on HHVM');
        }

        Block\await($this->browser->get('https://www.google.com/'));
    }

    /**
     * @group internet
     */
    public function testVerifyPeerEnabledForBadSslRejects()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on HHVM');
        }

        $connector = new Connector(array(
            'tls' => array(
                'verify_peer' => true
            )
        ));

        $browser = new Browser($connector);

        $this->setExpectedException('RuntimeException');
        Block\await($browser->get('https://self-signed.badssl.com/'));
    }

    /**
     * @group internet
     * @doesNotPerformAssertions
     */
    public function testVerifyPeerDisabledForBadSslResolves()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on HHVM');
        }

        $connector = new Connector(array(
            'tls' => array(
                'verify_peer' => false
            )
        ));

        $browser = new Browser($connector);

        Block\await($browser->get('https://self-signed.badssl.com/'));
    }

    /**
     * @group internet
     */
    public function testInvalidPort()
    {
        $this->setExpectedException('RuntimeException');
        Block\await($this->browser->get('http://www.google.com:443/'));
    }

    public function testErrorStatusCodeRejectsWithResponseException()
    {
        try {
            Block\await($this->browser->get($this->base . 'status/404'));
            $this->fail();
        } catch (ResponseException $e) {
            $this->assertEquals(404, $e->getCode());

            $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $e->getResponse());
            $this->assertEquals(404, $e->getResponse()->getStatusCode());
        }
    }

    public function testErrorStatusCodeDoesNotRejectWithRejectErrorResponseFalse()
    {
        $response = Block\await($this->browser->withRejectErrorResponse(false)->get($this->base . 'status/404'));

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testPostString()
    {
        $response = Block\await($this->browser->post($this->base . 'post', array(), 'hello world'));
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('hello world', $data['data']);
    }

    public function testRequestStreamReturnsResponseBodyUntilConnectionsEndsForHttp10()
    {
        $response = Block\await($this->browser->withProtocolVersion('1.0')->get($this->base . 'stream/1'));

        $this->assertEquals('1.0', $response->getProtocolVersion());
        $this->assertFalse($response->hasHeader('Transfer-Encoding'));

        $this->assertStringStartsWith('{', (string) $response->getBody());
        $this->assertStringEndsWith('}', (string) $response->getBody());
    }

    public function testRequestStreamReturnsResponseWithTransferEncodingChunkedAndResponseBodyDecodedForHttp11()
    {
        $response = Block\await($this->browser->get($this->base . 'stream/1'));

        $this->assertEquals('1.1', $response->getProtocolVersion());

        $this->assertEquals('chunked', $response->getHeaderLine('Transfer-Encoding'));

        $this->assertStringStartsWith('{', (string) $response->getBody());
        $this->assertStringEndsWith('}', (string) $response->getBody());
    }

    public function testRequestStreamWithHeadRequestReturnsEmptyResponseBodWithTransferEncodingChunkedForHttp11()
    {
        $response = Block\await($this->browser->head($this->base . 'stream/1'));

        $this->assertEquals('1.1', $response->getProtocolVersion());

        $this->assertEquals('chunked', $response->getHeaderLine('Transfer-Encoding'));
        $this->assertEquals('', (string) $response->getBody());
    }

    public function testRequestStreamReturnsResponseWithResponseBodyUndecodedWhenResponseHasDoubleTransferEncoding()
    {
        $socket = new SocketServer('127.0.0.1:0');
        $socket->on('connection', function (\React\Socket\ConnectionInterface $connection) {
            $connection->on('data', function () use ($connection) {
                $connection->end("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked, chunked\r\nConnection: close\r\n\r\nhello");
            });
        });

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $response = Block\await($this->browser->get($this->base . 'stream/1'));

        $this->assertEquals('1.1', $response->getProtocolVersion());

        $this->assertEquals('chunked, chunked', $response->getHeaderLine('Transfer-Encoding'));
        $this->assertEquals('hello', (string) $response->getBody());
    }

    public function testReceiveStreamAndExplicitlyCloseConnectionEvenWhenServerKeepsConnectionOpen()
    {
        $closed = new \React\Promise\Deferred();
        $socket = new SocketServer('127.0.0.1:0');
        $socket->on('connection', function (\React\Socket\ConnectionInterface $connection) use ($closed) {
            $connection->on('data', function () use ($connection) {
                $connection->write("HTTP/1.1 200 OK\r\nContent-Length: 5\r\n\r\nhello");
            });
            $connection->on('close', function () use ($closed) {
                $closed->resolve(true);
            });
        });

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $response = Block\await($this->browser->get($this->base . 'get', array()));
        $this->assertEquals('hello', (string)$response->getBody());

        $ret = Block\await($closed->promise(), null, 0.1);
        $this->assertTrue($ret);

        $socket->close();
    }

    public function testPostStreamChunked()
    {
        $stream = new ThroughStream();

        Loop::addTimer(0.001, function () use ($stream) {
            $stream->end('hello world');
        });

        $response = Block\await($this->browser->post($this->base . 'post', array(), $stream));
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('hello world', $data['data']);
        $this->assertFalse(isset($data['headers']['Content-Length']));
        $this->assertEquals('chunked', $data['headers']['Transfer-Encoding']);
    }

    public function testPostStreamKnownLength()
    {
        $stream = new ThroughStream();

        Loop::addTimer(0.001, function () use ($stream) {
            $stream->end('hello world');
        });

        $response = Block\await($this->browser->post($this->base . 'post', array('Content-Length' => 11), $stream));
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('hello world', $data['data']);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testPostStreamWillStartSendingRequestEvenWhenBodyDoesNotEmitData()
    {
        $http = new HttpServer(new StreamingRequestMiddleware(), function (ServerRequestInterface $request) {
            return new Response(200);
        });
        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $stream = new ThroughStream();
        Block\await($this->browser->post($this->base . 'post', array(), $stream));

        $socket->close();
    }

    public function testPostStreamClosed()
    {
        $stream = new ThroughStream();
        $stream->close();

        $response = Block\await($this->browser->post($this->base . 'post', array(), $stream));
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('', $data['data']);
    }

    public function testSendsHttp11ByDefault()
    {
        $http = new HttpServer(function (ServerRequestInterface $request) {
            return new Response(
                200,
                array(),
                $request->getProtocolVersion()
            );
        });
        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $response = Block\await($this->browser->get($this->base));
        $this->assertEquals('1.1', (string)$response->getBody());

        $socket->close();
    }

    public function testSendsExplicitHttp10Request()
    {
        $http = new HttpServer(function (ServerRequestInterface $request) {
            return new Response(
                200,
                array(),
                $request->getProtocolVersion()
            );
        });
        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $response = Block\await($this->browser->withProtocolVersion('1.0')->get($this->base));
        $this->assertEquals('1.0', (string)$response->getBody());

        $socket->close();
    }

    public function testHeadRequestReceivesResponseWithEmptyBodyButWithContentLengthResponseHeader()
    {
        $response = Block\await($this->browser->head($this->base . 'get'));
        $this->assertEquals('5', $response->getHeaderLine('Content-Length'));

        $body = $response->getBody();
        $this->assertEquals(0, $body->getSize());
        $this->assertEquals('', (string) $body);
    }

    public function testRequestStreamingGetReceivesResponseWithStreamingBodyAndKnownSize()
    {
        $response = Block\await($this->browser->requestStreaming('GET', $this->base . 'get'));
        $this->assertEquals('5', $response->getHeaderLine('Content-Length'));

        $body = $response->getBody();
        $this->assertEquals(5, $body->getSize());
        $this->assertEquals('', (string) $body);
        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);
    }

    public function testRequestStreamingGetReceivesResponseWithStreamingBodyAndUnknownSizeFromStreamingEndpoint()
    {
        $response = Block\await($this->browser->requestStreaming('GET', $this->base . 'stream/1'));
        $this->assertFalse($response->hasHeader('Content-Length'));

        $body = $response->getBody();
        $this->assertNull($body->getSize());
        $this->assertEquals('', (string) $body);
        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);
    }

    public function testRequestStreamingGetReceivesStreamingResponseBody()
    {
        $buffer = Block\await(
            $this->browser->requestStreaming('GET', $this->base . 'get')->then(function (ResponseInterface $response) {
                return Stream\buffer($response->getBody());
            })
        );

        $this->assertEquals('hello', $buffer);
    }

    public function testRequestStreamingGetReceivesStreamingResponseBodyEvenWhenResponseBufferExceeded()
    {
        $buffer = Block\await(
            $this->browser->withResponseBuffer(4)->requestStreaming('GET', $this->base . 'get')->then(function (ResponseInterface $response) {
                return Stream\buffer($response->getBody());
            })
        );

        $this->assertEquals('hello', $buffer);
    }
}
