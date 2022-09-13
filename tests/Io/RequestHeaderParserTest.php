<?php

namespace React\Tests\Http\Io;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\Clock;
use React\Http\Io\RequestHeaderParser;
use React\Tests\Http\TestCase;

class RequestHeaderParserTest extends TestCase
{
    public function testSplitShouldHappenOnDoubleCrlf()
    {
        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();

        $parser->handle($connection);

        $connection->emit('data', array("GET / HTTP/1.1\r\n"));
        $connection->emit('data', array("Host: example.com:80\r\n"));
        $connection->emit('data', array("Connection: close\r\n"));

        $parser->removeAllListeners();
        $parser->on('headers', $this->expectCallableOnce());

        $connection->emit('data', array("\r\n"));
    }

    public function testFeedInOneGo()
    {
        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableOnce());

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $data = $this->createGetRequest();
        $connection->emit('data', array($data));
    }

    public function testFeedTwoRequestsOnSeparateConnections()
    {
        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);

        $called = 0;
        $parser->on('headers', function () use (&$called) {
            ++$called;
        });

        $connection1 = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $connection2 = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection1);
        $parser->handle($connection2);

        $data = $this->createGetRequest();
        $connection1->emit('data', array($data));
        $connection2->emit('data', array($data));

        $this->assertEquals(2, $called);
    }

    public function testHeadersEventShouldEmitRequestAndConnection()
    {
        $request = null;
        $conn = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', function ($parsedRequest, $connection) use (&$request, &$conn) {
            $request = $parsedRequest;
            $conn = $connection;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $data = $this->createGetRequest();
        $connection->emit('data', array($data));

        $this->assertInstanceOf('Psr\Http\Message\RequestInterface', $request);
        $this->assertSame('GET', $request->getMethod());
        $this->assertEquals('http://example.com/', $request->getUri());
        $this->assertSame('1.1', $request->getProtocolVersion());
        $this->assertSame(array('Host' => array('example.com'), 'Connection' => array('close')), $request->getHeaders());

        $this->assertSame($connection, $conn);
    }

    public function testHeadersEventShouldEmitRequestWhichShouldEmitEndForStreamingBodyWithoutContentLengthFromInitialRequestBody()
    {
        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);

        $ended = false;
        $that = $this;
        $parser->on('headers', function (ServerRequestInterface $request) use (&$ended, $that) {
            $body = $request->getBody();
            $that->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);

            $body->on('end', function () use (&$ended) {
                $ended = true;
            });
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $data = "GET / HTTP/1.0\r\n\r\n";
        $connection->emit('data', array($data));

        $this->assertTrue($ended);
    }

    public function testHeadersEventShouldEmitRequestWhichShouldEmitStreamingBodyDataFromInitialRequestBody()
    {
        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);

        $buffer = '';
        $that = $this;
        $parser->on('headers', function (ServerRequestInterface $request) use (&$buffer, $that) {
            $body = $request->getBody();
            $that->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);

            $body->on('data', function ($chunk) use (&$buffer) {
                $buffer .= $chunk;
            });
            $body->on('end', function () use (&$buffer) {
                $buffer .= '.';
            });
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $data = "POST / HTTP/1.0\r\nContent-Length: 11\r\n\r\n";
        $data .= 'RANDOM DATA';
        $connection->emit('data', array($data));

        $this->assertSame('RANDOM DATA.', $buffer);
    }

    public function testHeadersEventShouldEmitRequestWhichShouldEmitStreamingBodyWithPlentyOfDataFromInitialRequestBody()
    {
        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);

        $buffer = '';
        $that = $this;
        $parser->on('headers', function (ServerRequestInterface $request) use (&$buffer, $that) {
            $body = $request->getBody();
            $that->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);

            $body->on('data', function ($chunk) use (&$buffer) {
                $buffer .= $chunk;
            });
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $size = 10000;
        $data = "POST / HTTP/1.0\r\nContent-Length: $size\r\n\r\n";
        $data .= str_repeat('x', $size);
        $connection->emit('data', array($data));

        $this->assertSame($size, strlen($buffer));
    }

    public function testHeadersEventShouldEmitRequestWhichShouldNotEmitStreamingBodyDataWithoutContentLengthFromInitialRequestBody()
    {
        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);

        $buffer = '';
        $that = $this;
        $parser->on('headers', function (ServerRequestInterface $request) use (&$buffer, $that) {
            $body = $request->getBody();
            $that->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);

            $body->on('data', function ($chunk) use (&$buffer) {
                $buffer .= $chunk;
            });
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $data = "POST / HTTP/1.0\r\n\r\n";
        $data .= 'RANDOM DATA';
        $connection->emit('data', array($data));

        $this->assertSame('', $buffer);
    }

    public function testHeadersEventShouldEmitRequestWhichShouldEmitStreamingBodyDataUntilContentLengthBoundaryFromInitialRequestBody()
    {
        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);

        $buffer = '';
        $that = $this;
        $parser->on('headers', function (ServerRequestInterface $request) use (&$buffer, $that) {
            $body = $request->getBody();
            $that->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);

            $body->on('data', function ($chunk) use (&$buffer) {
                $buffer .= $chunk;
            });
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $data = "POST / HTTP/1.0\r\nContent-Length: 6\r\n\r\n";
        $data .= 'RANDOM DATA';
        $connection->emit('data', array($data));

        $this->assertSame('RANDOM', $buffer);
    }

    public function testHeadersEventShouldParsePathAndQueryString()
    {
        $request = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $data = $this->createAdvancedPostRequest();
        $connection->emit('data', array($data));

        $this->assertInstanceOf('Psr\Http\Message\RequestInterface', $request);
        $this->assertSame('POST', $request->getMethod());
        $this->assertEquals('http://example.com/foo?bar=baz', $request->getUri());
        $this->assertSame('1.1', $request->getProtocolVersion());
        $headers = array(
            'Host' => array('example.com'),
            'User-Agent' => array('react/alpha'),
            'Connection' => array('close'),
        );
        $this->assertSame($headers, $request->getHeaders());
    }

    public function testHeaderEventWithShouldApplyDefaultAddressFromLocalConnectionAddress()
    {
        $request = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('getLocalAddress'))->getMock();
        $connection->expects($this->once())->method('getLocalAddress')->willReturn('tcp://127.1.1.1:8000');
        $parser->handle($connection);

        $connection->emit('data', array("GET /foo HTTP/1.0\r\n\r\n"));

        $this->assertEquals('http://127.1.1.1:8000/foo', $request->getUri());
        $this->assertFalse($request->hasHeader('Host'));
    }

    public function testHeaderEventViaHttpsShouldApplyHttpsSchemeFromLocalTlsConnectionAddress()
    {
        $request = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('getLocalAddress'))->getMock();
        $connection->expects($this->once())->method('getLocalAddress')->willReturn('tls://127.1.1.1:8000');
        $parser->handle($connection);

        $connection->emit('data', array("GET /foo HTTP/1.0\r\nHost: example.com\r\n\r\n"));

        $this->assertEquals('https://example.com/foo', $request->getUri());
        $this->assertEquals('example.com', $request->getHeaderLine('Host'));
    }

    public function testHeaderOverflowShouldEmitError()
    {
        $error = null;
        $passedConnection = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message, $connection) use (&$error, &$passedConnection) {
            $error = $message;
            $passedConnection = $connection;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $data = str_repeat('A', 8193);
        $connection->emit('data', array($data));

        $this->assertInstanceOf('OverflowException', $error);
        $this->assertSame('Maximum header size of 8192 exceeded.', $error->getMessage());
        $this->assertSame($connection, $passedConnection);
    }

    public function testInvalidEmptyRequestHeadersParseException()
    {
        $error = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("\r\n\r\n"));

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Unable to parse invalid request-line', $error->getMessage());
    }

    public function testInvalidMalformedRequestLineParseException()
    {
        $error = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET /\r\n\r\n"));

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Unable to parse invalid request-line', $error->getMessage());
    }

    public function testInvalidMalformedRequestHeadersThrowsParseException()
    {
        $error = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET / HTTP/1.1\r\nHost : yes\r\n\r\n"));

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Unable to parse invalid request header fields', $error->getMessage());
    }

    public function testInvalidMalformedRequestHeadersWhitespaceThrowsParseException()
    {
        $error = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET / HTTP/1.1\r\nHost: yes\rFoo: bar\r\n\r\n"));

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Unable to parse invalid request header fields', $error->getMessage());
    }

    public function testInvalidAbsoluteFormSchemeEmitsError()
    {
        $error = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET tcp://example.com:80/ HTTP/1.0\r\n\r\n"));

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Invalid absolute-form request-target', $error->getMessage());
    }

    public function testOriginFormWithSchemeSeparatorInParam()
    {
        $request = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('error', $this->expectCallableNever());
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$request) {
            $request = $parsedRequest;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET /somepath?param=http://example.com HTTP/1.1\r\nHost: localhost\r\n\r\n"));

        $this->assertInstanceOf('Psr\Http\Message\RequestInterface', $request);
        $this->assertSame('GET', $request->getMethod());
        $this->assertEquals('http://localhost/somepath?param=http://example.com', $request->getUri());
        $this->assertSame('1.1', $request->getProtocolVersion());
        $headers = array(
            'Host' => array('localhost')
        );
        $this->assertSame($headers, $request->getHeaders());
    }

    public function testUriStartingWithColonSlashSlashFails()
    {
        $error = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET ://example.com:80/ HTTP/1.0\r\n\r\n"));

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Invalid absolute-form request-target', $error->getMessage());
    }

    public function testInvalidAbsoluteFormWithFragmentEmitsError()
    {
        $error = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET http://example.com:80/#home HTTP/1.0\r\n\r\n"));

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Invalid absolute-form request-target', $error->getMessage());
    }

    public function testInvalidHeaderContainsFullUri()
    {
        $error = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET / HTTP/1.1\r\nHost: http://user:pass@host/\r\n\r\n"));

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Invalid Host header value', $error->getMessage());
    }

    public function testInvalidAbsoluteFormWithHostHeaderEmpty()
    {
        $error = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET http://example.com/ HTTP/1.1\r\nHost: \r\n\r\n"));

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Invalid Host header value', $error->getMessage());
    }

    public function testInvalidConnectRequestWithNonAuthorityForm()
    {
        $error = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("CONNECT http://example.com:8080/ HTTP/1.1\r\nHost: example.com:8080\r\n\r\n"));

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('CONNECT method MUST use authority-form request target', $error->getMessage());
    }

    public function testInvalidHttpVersion()
    {
        $error = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET / HTTP/1.2\r\n\r\n"));

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame(505, $error->getCode());
        $this->assertSame('Received request with invalid protocol version', $error->getMessage());
    }

    public function testInvalidContentLengthRequestHeaderWillEmitError()
    {
        $error = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET / HTTP/1.1\r\nHost: localhost\r\nContent-Length: foo\r\n\r\n"));

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame(400, $error->getCode());
        $this->assertSame('The value of `Content-Length` is not valid', $error->getMessage());
    }

    public function testInvalidRequestWithMultipleContentLengthRequestHeadersWillEmitError()
    {
        $error = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET / HTTP/1.1\r\nHost: localhost\r\nContent-Length: 4\r\nContent-Length: 5\r\n\r\n"));

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame(400, $error->getCode());
        $this->assertSame('The value of `Content-Length` is not valid', $error->getMessage());
    }

    public function testInvalidTransferEncodingRequestHeaderWillEmitError()
    {
        $error = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET / HTTP/1.1\r\nHost: localhost\r\nTransfer-Encoding: foo\r\n\r\n"));

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame(501, $error->getCode());
        $this->assertSame('Only chunked-encoding is allowed for Transfer-Encoding', $error->getMessage());
    }

    public function testInvalidRequestWithBothTransferEncodingAndContentLengthWillEmitError()
    {
        $error = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET / HTTP/1.1\r\nHost: localhost\r\nTransfer-Encoding: chunked\r\nContent-Length: 0\r\n\r\n"));

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame(400, $error->getCode());
        $this->assertSame('Using both `Transfer-Encoding: chunked` and `Content-Length` is not allowed', $error->getMessage());
    }

    public function testServerParamsWillBeSetOnHttpsRequest()
    {
        $request = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();
        $clock->expects($this->once())->method('now')->willReturn(1652972091.3958);

        $parser = $this->createRequestHeaderParser($clock);

        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('getLocalAddress', 'getRemoteAddress'))->getMock();
        $connection->expects($this->once())->method('getLocalAddress')->willReturn('tls://127.1.1.1:8000');
        $connection->expects($this->once())->method('getRemoteAddress')->willReturn('tls://192.168.1.1:8001');
        $parser->handle($connection);

        $connection->emit('data', array("GET /foo HTTP/1.0\r\nHost: example.com\r\n\r\n"));

        $serverParams = $request->getServerParams();

        $this->assertEquals('on', $serverParams['HTTPS']);
        $this->assertEquals(1652972091, $serverParams['REQUEST_TIME']);
        $this->assertEquals(1652972091.3958, $serverParams['REQUEST_TIME_FLOAT']);

        $this->assertEquals('127.1.1.1', $serverParams['SERVER_ADDR']);
        $this->assertEquals('8000', $serverParams['SERVER_PORT']);

        $this->assertEquals('192.168.1.1', $serverParams['REMOTE_ADDR']);
        $this->assertEquals('8001', $serverParams['REMOTE_PORT']);
    }

    public function testServerParamsWillBeSetOnHttpRequest()
    {
        $request = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();
        $clock->expects($this->once())->method('now')->willReturn(1652972091.3958);

        $parser = $this->createRequestHeaderParser($clock);

        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('getLocalAddress', 'getRemoteAddress'))->getMock();
        $connection->expects($this->once())->method('getLocalAddress')->willReturn('tcp://127.1.1.1:8000');
        $connection->expects($this->once())->method('getRemoteAddress')->willReturn('tcp://192.168.1.1:8001');
        $parser->handle($connection);

        $connection->emit('data', array("GET /foo HTTP/1.0\r\nHost: example.com\r\n\r\n"));

        $serverParams = $request->getServerParams();

        $this->assertArrayNotHasKey('HTTPS', $serverParams);
        $this->assertEquals(1652972091, $serverParams['REQUEST_TIME']);
        $this->assertEquals(1652972091.3958, $serverParams['REQUEST_TIME_FLOAT']);

        $this->assertEquals('127.1.1.1', $serverParams['SERVER_ADDR']);
        $this->assertEquals('8000', $serverParams['SERVER_PORT']);

        $this->assertEquals('192.168.1.1', $serverParams['REMOTE_ADDR']);
        $this->assertEquals('8001', $serverParams['REMOTE_PORT']);
    }

    public function testServerParamsWillNotSetRemoteAddressForUnixDomainSockets()
    {
        $request = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();
        $clock->expects($this->once())->method('now')->willReturn(1652972091.3958);

        $parser = $this->createRequestHeaderParser($clock);

        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('getLocalAddress', 'getRemoteAddress'))->getMock();
        $connection->expects($this->once())->method('getLocalAddress')->willReturn('unix://./server.sock');
        $connection->expects($this->once())->method('getRemoteAddress')->willReturn(null);
        $parser->handle($connection);

        $connection->emit('data', array("GET /foo HTTP/1.0\r\nHost: example.com\r\n\r\n"));

        $serverParams = $request->getServerParams();

        $this->assertArrayNotHasKey('HTTPS', $serverParams);
        $this->assertEquals(1652972091, $serverParams['REQUEST_TIME']);
        $this->assertEquals(1652972091.3958, $serverParams['REQUEST_TIME_FLOAT']);

        $this->assertArrayNotHasKey('SERVER_ADDR', $serverParams);
        $this->assertArrayNotHasKey('SERVER_PORT', $serverParams);

        $this->assertArrayNotHasKey('REMOTE_ADDR', $serverParams);
        $this->assertArrayNotHasKey('REMOTE_PORT', $serverParams);
    }

    public function testServerParamsWontBeSetOnMissingUrls()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on HHVM');
        }

        $request = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();
        $clock->expects($this->once())->method('now')->willReturn(1652972091.3958);

        $parser = $this->createRequestHeaderParser($clock);

        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET /foo HTTP/1.0\r\nHost: example.com\r\n\r\n"));

        $serverParams = $request->getServerParams();

        $this->assertEquals(1652972091, $serverParams['REQUEST_TIME']);
        $this->assertEquals(1652972091.3958, $serverParams['REQUEST_TIME_FLOAT']);

        $this->assertArrayNotHasKey('SERVER_ADDR', $serverParams);
        $this->assertArrayNotHasKey('SERVER_PORT', $serverParams);

        $this->assertArrayNotHasKey('REMOTE_ADDR', $serverParams);
        $this->assertArrayNotHasKey('REMOTE_PORT', $serverParams);
    }

    public function testServerParamsWillBeReusedForMultipleRequestsFromSameConnection()
    {
        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();
        $clock->expects($this->exactly(2))->method('now')->willReturn(1652972091.3958);

        $parser = new RequestHeaderParser($clock);

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('getLocalAddress', 'getRemoteAddress'))->getMock();
        $connection->expects($this->once())->method('getLocalAddress')->willReturn('tcp://127.1.1.1:8000');
        $connection->expects($this->once())->method('getRemoteAddress')->willReturn('tcp://192.168.1.1:8001');

        $parser->handle($connection);
        $connection->emit('data', array("GET /foo HTTP/1.0\r\nHost: example.com\r\n\r\n"));

        $request = null;
        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $parser->handle($connection);
        $connection->emit('data', array("GET /foo HTTP/1.0\r\nHost: example.com\r\n\r\n"));

        assert($request instanceof ServerRequestInterface);
        $serverParams = $request->getServerParams();

        $this->assertArrayNotHasKey('HTTPS', $serverParams);
        $this->assertEquals(1652972091, $serverParams['REQUEST_TIME']);
        $this->assertEquals(1652972091.3958, $serverParams['REQUEST_TIME_FLOAT']);

        $this->assertEquals('127.1.1.1', $serverParams['SERVER_ADDR']);
        $this->assertEquals('8000', $serverParams['SERVER_PORT']);

        $this->assertEquals('192.168.1.1', $serverParams['REMOTE_ADDR']);
        $this->assertEquals('8001', $serverParams['REMOTE_PORT']);
    }

    public function testServerParamsWillBeRememberedUntilConnectionIsClosed()
    {
        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = new RequestHeaderParser($clock);

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('getLocalAddress', 'getRemoteAddress'))->getMock();

        $parser->handle($connection);
        $connection->emit('data', array("GET /foo HTTP/1.0\r\nHost: example.com\r\n\r\n"));

        $ref = new \ReflectionProperty($parser, 'connectionParams');
        $ref->setAccessible(true);

        $this->assertCount(1, $ref->getValue($parser));

        $connection->emit('close');
        $this->assertEquals(array(), $ref->getValue($parser));
    }

    public function testQueryParmetersWillBeSet()
    {
        $request = null;

        $clock = $this->getMockBuilder('React\Http\Io\Clock')->disableOriginalConstructor()->getMock();

        $parser = $this->createRequestHeaderParser($clock);

        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(null)->getMock();
        $parser->handle($connection);

        $connection->emit('data', array("GET /foo.php?hello=world&test=this HTTP/1.0\r\nHost: example.com\r\n\r\n"));

        $queryParams = $request->getQueryParams();

        $this->assertEquals('world', $queryParams['hello']);
        $this->assertEquals('this', $queryParams['test']);
    }

    private function createGetRequest()
    {
        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "\r\n";

        return $data;
    }

    private function createAdvancedPostRequest()
    {
        $data = "POST /foo?bar=baz HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "User-Agent: react/alpha\r\n";
        $data .= "Connection: close\r\n";
        $data .= "\r\n";

        return $data;
    }

    private function createRequestHeaderParser(Clock $clock)
    {
        return new RequestHeaderParser($clock);
    }
}
