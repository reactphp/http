<?php

namespace React\Tests\Http;

use Clue\React\Block;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Browser;
use React\Http\Message\ResponseException;
use React\Http\Middleware\StreamingRequestMiddleware;
use React\Http\Response;
use React\Http\Server;
use React\Promise\Promise;
use React\Promise\Stream;
use React\Socket\Connector;
use React\Stream\ReadableStreamInterface;
use React\Stream\ThroughStream;
use RingCentral\Psr7\Request;

class FunctionalBrowserTest extends TestCase
{
    private $loop;
    private $browser;
    private $base;

    /**
     * @before
     */
    public function setUpBrowserAndServer()
    {
        $this->loop = $loop = Factory::create();
        $this->browser = new Browser($this->loop);

        $server = new Server($this->loop, new StreamingRequestMiddleware(), function (ServerRequestInterface $request) use ($loop) {
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

            if ($path === '/status/300') {
                return new Response(
                    300,
                    array(),
                    ''
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
                return new Promise(function ($resolve) use ($loop) {
                    $loop->addTimer(10, function () use ($resolve) {
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

                $loop->futureTick(function () use ($stream, $headers) {
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
        $socket = new \React\Socket\Server(0, $this->loop);
        $server->listen($socket);

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testSimpleRequest()
    {
        Block\await($this->browser->get($this->base . 'get'), $this->loop);
    }

    public function testGetRequestWithRelativeAddressRejects()
    {
        $promise = $this->browser->get('delay');

        $this->setExpectedException('InvalidArgumentException', 'Invalid request URL given');
        Block\await($promise, $this->loop);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testGetRequestWithBaseAndRelativeAddressResolves()
    {
        Block\await($this->browser->withBase($this->base)->get('get'), $this->loop);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testGetRequestWithBaseAndFullAddressResolves()
    {
        Block\await($this->browser->withBase('http://example.com/')->get($this->base . 'get'), $this->loop);
    }

    public function testCancelGetRequestWillRejectRequest()
    {
        $promise = $this->browser->get($this->base . 'get');
        $promise->cancel();

        $this->setExpectedException('RuntimeException');
        Block\await($promise, $this->loop);
    }

    public function testCancelRequestWithPromiseFollowerWillRejectRequest()
    {
        $promise = $this->browser->request('GET', $this->base . 'get')->then(function () {
            var_dump('noop');
        });
        $promise->cancel();

        $this->setExpectedException('RuntimeException');
        Block\await($promise, $this->loop);
    }

    public function testRequestWithoutAuthenticationFails()
    {
        $this->setExpectedException('RuntimeException');
        Block\await($this->browser->get($this->base . 'basic-auth/user/pass'), $this->loop);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRequestWithAuthenticationSucceeds()
    {
        $base = str_replace('://', '://user:pass@', $this->base);

        Block\await($this->browser->get($base . 'basic-auth/user/pass'), $this->loop);
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

        Block\await($this->browser->get($this->base . 'redirect-to?url=' . urlencode($target)), $this->loop);
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

        Block\await($this->browser->get($base . 'redirect-to?url=' . urlencode($target)), $this->loop);
    }

    public function testCancelRedirectedRequestShouldReject()
    {
        $promise = $this->browser->get($this->base . 'redirect-to?url=delay%2F10');

        $this->loop->addTimer(0.1, function () use ($promise) {
            $promise->cancel();
        });

        $this->setExpectedException('RuntimeException', 'Request cancelled');
        Block\await($promise, $this->loop);
    }

    public function testTimeoutDelayedResponseShouldReject()
    {
        $promise = $this->browser->withTimeout(0.1)->get($this->base . 'delay/10');

        $this->setExpectedException('RuntimeException', 'Request timed out after 0.1 seconds');
        Block\await($promise, $this->loop);
    }

    public function testTimeoutDelayedResponseAfterStreamingRequestShouldReject()
    {
        $stream = new ThroughStream();
        $promise = $this->browser->withTimeout(0.1)->post($this->base . 'delay/10', array(), $stream);
        $stream->end();

        $this->setExpectedException('RuntimeException', 'Request timed out after 0.1 seconds');
        Block\await($promise, $this->loop);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testTimeoutFalseShouldResolveSuccessfully()
    {
        Block\await($this->browser->withTimeout(false)->get($this->base . 'get'), $this->loop);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRedirectRequestRelative()
    {
        Block\await($this->browser->get($this->base . 'redirect-to?url=get'), $this->loop);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRedirectRequestAbsolute()
    {
        Block\await($this->browser->get($this->base . 'redirect-to?url=' . urlencode($this->base . 'get')), $this->loop);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFollowingRedirectsFalseResolvesWithRedirectResult()
    {
        $browser = $this->browser->withFollowRedirects(false);

        Block\await($browser->get($this->base . 'redirect-to?url=get'), $this->loop);
    }

    public function testFollowRedirectsZeroRejectsOnRedirect()
    {
        $browser = $this->browser->withFollowRedirects(0);

        $this->setExpectedException('RuntimeException');
        Block\await($browser->get($this->base . 'redirect-to?url=get'), $this->loop);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testResponseStatus300WithoutLocationShouldResolveWithoutFollowingRedirect()
    {
        Block\await($this->browser->get($this->base . 'status/300'), $this->loop);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testGetRequestWithResponseBufferMatchedExactlyResolves()
    {
        $promise = $this->browser->withResponseBuffer(5)->get($this->base . 'get');

        Block\await($promise, $this->loop);
    }

    public function testGetRequestWithResponseBufferExceededRejects()
    {
        $promise = $this->browser->withResponseBuffer(4)->get($this->base . 'get');

        $this->setExpectedException(
            'OverflowException',
            'Response body size of 5 bytes exceeds maximum of 4 bytes',
            defined('SOCKET_EMSGSIZE') ? SOCKET_EMSGSIZE : 0
        );
        Block\await($promise, $this->loop);
    }

    public function testGetRequestWithResponseBufferExceededDuringStreamingRejects()
    {
        $promise = $this->browser->withResponseBuffer(4)->get($this->base . 'stream/1');

        $this->setExpectedException(
            'OverflowException',
            'Response body size exceeds maximum of 4 bytes',
            defined('SOCKET_EMSGSIZE') ? SOCKET_EMSGSIZE : 0
        );
        Block\await($promise, $this->loop);
    }

    /**
     * @group online
     * @doesNotPerformAssertions
     */
    public function testCanAccessHttps()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        Block\await($this->browser->get('https://www.google.com/'), $this->loop);
    }

    /**
     * @group online
     */
    public function testVerifyPeerEnabledForBadSslRejects()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        $connector = new Connector($this->loop, array(
            'tls' => array(
                'verify_peer' => true
            )
        ));

        $browser = new Browser($this->loop, $connector);

        $this->setExpectedException('RuntimeException');
        Block\await($browser->get('https://self-signed.badssl.com/'), $this->loop);
    }

    /**
     * @group online
     * @doesNotPerformAssertions
     */
    public function testVerifyPeerDisabledForBadSslResolves()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        $connector = new Connector($this->loop, array(
            'tls' => array(
                'verify_peer' => false
            )
        ));

        $browser = new Browser($this->loop, $connector);

        Block\await($browser->get('https://self-signed.badssl.com/'), $this->loop);
    }

    /**
     * @group online
     */
    public function testInvalidPort()
    {
        $this->setExpectedException('RuntimeException');
        Block\await($this->browser->get('http://www.google.com:443/'), $this->loop);
    }

    public function testErrorStatusCodeRejectsWithResponseException()
    {
        try {
            Block\await($this->browser->get($this->base . 'status/404'), $this->loop);
            $this->fail();
        } catch (ResponseException $e) {
            $this->assertEquals(404, $e->getCode());

            $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $e->getResponse());
            $this->assertEquals(404, $e->getResponse()->getStatusCode());
        }
    }

    public function testErrorStatusCodeDoesNotRejectWithRejectErrorResponseFalse()
    {
        $response = Block\await($this->browser->withRejectErrorResponse(false)->get($this->base . 'status/404'), $this->loop);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testPostString()
    {
        $response = Block\await($this->browser->post($this->base . 'post', array(), 'hello world'), $this->loop);
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('hello world', $data['data']);
    }

    public function testReceiveStreamUntilConnectionsEndsForHttp10()
    {
        $response = Block\await($this->browser->withProtocolVersion('1.0')->get($this->base . 'stream/1'), $this->loop);

        $this->assertEquals('1.0', $response->getProtocolVersion());
        $this->assertFalse($response->hasHeader('Transfer-Encoding'));

        $this->assertStringStartsWith('{', (string) $response->getBody());
        $this->assertStringEndsWith('}', (string) $response->getBody());
    }

    public function testReceiveStreamChunkedForHttp11()
    {
        $response = Block\await($this->browser->request('GET', $this->base . 'stream/1'), $this->loop);

        $this->assertEquals('1.1', $response->getProtocolVersion());

        // underlying http-client automatically decodes and doesn't expose header
        // @link https://github.com/reactphp/http-client/pull/58
        // $this->assertEquals('chunked', $response->getHeaderLine('Transfer-Encoding'));
        $this->assertFalse($response->hasHeader('Transfer-Encoding'));

        $this->assertStringStartsWith('{', (string) $response->getBody());
        $this->assertStringEndsWith('}', (string) $response->getBody());
    }

    public function testReceiveStreamAndExplicitlyCloseConnectionEvenWhenServerKeepsConnectionOpen()
    {
        $closed = new \React\Promise\Deferred();
        $socket = new \React\Socket\Server(0, $this->loop);
        $socket->on('connection', function (\React\Socket\ConnectionInterface $connection) use ($closed) {
            $connection->on('data', function () use ($connection) {
                $connection->write("HTTP/1.1 200 OK\r\nContent-Length: 5\r\n\r\nhello");
            });
            $connection->on('close', function () use ($closed) {
                $closed->resolve(true);
            });
        });

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $response = Block\await($this->browser->get($this->base . 'get', array()), $this->loop);
        $this->assertEquals('hello', (string)$response->getBody());

        $ret = Block\await($closed->promise(), $this->loop, 0.1);
        $this->assertTrue($ret);

        $socket->close();
    }

    public function testPostStreamChunked()
    {
        $stream = new ThroughStream();

        $this->loop->addTimer(0.001, function () use ($stream) {
            $stream->end('hello world');
        });

        $response = Block\await($this->browser->post($this->base . 'post', array(), $stream), $this->loop);
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('hello world', $data['data']);
        $this->assertFalse(isset($data['headers']['Content-Length']));
        $this->assertEquals('chunked', $data['headers']['Transfer-Encoding']);
    }

    public function testPostStreamKnownLength()
    {
        $stream = new ThroughStream();

        $this->loop->addTimer(0.001, function () use ($stream) {
            $stream->end('hello world');
        });

        $response = Block\await($this->browser->post($this->base . 'post', array('Content-Length' => 11), $stream), $this->loop);
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('hello world', $data['data']);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testPostStreamWillStartSendingRequestEvenWhenBodyDoesNotEmitData()
    {
        $server = new Server($this->loop, new StreamingRequestMiddleware(), function (ServerRequestInterface $request) {
            return new Response(200);
        });
        $socket = new \React\Socket\Server(0, $this->loop);
        $server->listen($socket);

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $stream = new ThroughStream();
        Block\await($this->browser->post($this->base . 'post', array(), $stream), $this->loop);

        $socket->close();
    }

    public function testPostStreamClosed()
    {
        $stream = new ThroughStream();
        $stream->close();

        $response = Block\await($this->browser->post($this->base . 'post', array(), $stream), $this->loop);
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('', $data['data']);
    }

    public function testSendsHttp11ByDefault()
    {
        $server = new Server($this->loop, function (ServerRequestInterface $request) {
            return new Response(
                200,
                array(),
                $request->getProtocolVersion()
            );
        });
        $socket = new \React\Socket\Server(0, $this->loop);
        $server->listen($socket);

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $response = Block\await($this->browser->get($this->base), $this->loop);
        $this->assertEquals('1.1', (string)$response->getBody());

        $socket->close();
    }

    public function testSendsExplicitHttp10Request()
    {
        $server = new Server($this->loop, function (ServerRequestInterface $request) {
            return new Response(
                200,
                array(),
                $request->getProtocolVersion()
            );
        });
        $socket = new \React\Socket\Server(0, $this->loop);
        $server->listen($socket);

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $response = Block\await($this->browser->withProtocolVersion('1.0')->get($this->base), $this->loop);
        $this->assertEquals('1.0', (string)$response->getBody());

        $socket->close();
    }

    public function testHeadRequestReceivesResponseWithEmptyBodyButWithContentLengthResponseHeader()
    {
        $response = Block\await($this->browser->head($this->base . 'get'), $this->loop);
        $this->assertEquals('', (string)$response->getBody());
        $this->assertEquals(0, $response->getBody()->getSize());
        $this->assertEquals('5', $response->getHeaderLine('Content-Length'));
    }

    public function testRequestStreamingGetReceivesStreamingResponseBody()
    {
        $buffer = Block\await(
            $this->browser->requestStreaming('GET', $this->base . 'get')->then(function (ResponseInterface $response) {
                return Stream\buffer($response->getBody());
            }),
            $this->loop
        );

        $this->assertEquals('hello', $buffer);
    }

    public function testRequestStreamingGetReceivesStreamingResponseBodyEvenWhenResponseBufferExceeded()
    {
        $buffer = Block\await(
            $this->browser->withResponseBuffer(4)->requestStreaming('GET', $this->base . 'get')->then(function (ResponseInterface $response) {
                return Stream\buffer($response->getBody());
            }),
            $this->loop
        );

        $this->assertEquals('hello', $buffer);
    }
}
