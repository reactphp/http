<?php

namespace React\Tests\Http\Io;

use React\Http\Io\RequestHeaderParser;
use React\Tests\Http\TestCase;

class RequestHeaderParserTest extends TestCase
{
    public function testSplitShouldHappenOnDoubleCrlf()
    {
        $parser = new RequestHeaderParser();
        $parser->on('headers', $this->expectCallableNever());

        $parser->feed("GET / HTTP/1.1\r\n");
        $parser->feed("Host: example.com:80\r\n");
        $parser->feed("Connection: close\r\n");

        $parser->removeAllListeners();
        $parser->on('headers', $this->expectCallableOnce());

        $parser->feed("\r\n");
    }

    public function testFeedInOneGo()
    {
        $parser = new RequestHeaderParser();
        $parser->on('headers', $this->expectCallableOnce());

        $data = $this->createGetRequest();
        $parser->feed($data);
    }

    public function testHeadersEventShouldReturnRequestAndBodyBuffer()
    {
        $request = null;
        $bodyBuffer = null;

        $parser = new RequestHeaderParser();
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$request, &$bodyBuffer) {
            $request = $parsedRequest;
            $bodyBuffer = $parsedBodyBuffer;
        });

        $data = $this->createGetRequest();
        $data .= 'RANDOM DATA';
        $parser->feed($data);

        $this->assertInstanceOf('Psr\Http\Message\RequestInterface', $request);
        $this->assertSame('GET', $request->getMethod());
        $this->assertEquals('http://example.com/', $request->getUri());
        $this->assertSame('1.1', $request->getProtocolVersion());
        $this->assertSame(array('Host' => array('example.com'), 'Connection' => array('close')), $request->getHeaders());

        $this->assertSame('RANDOM DATA', $bodyBuffer);
    }

    public function testHeadersEventShouldReturnBinaryBodyBuffer()
    {
        $bodyBuffer = null;

        $parser = new RequestHeaderParser();
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$bodyBuffer) {
            $bodyBuffer = $parsedBodyBuffer;
        });

        $data = $this->createGetRequest();
        $data .= "\0x01\0x02\0x03\0x04\0x05";
        $parser->feed($data);

        $this->assertSame("\0x01\0x02\0x03\0x04\0x05", $bodyBuffer);
    }

    public function testHeadersEventShouldParsePathAndQueryString()
    {
        $request = null;

        $parser = new RequestHeaderParser();
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$request) {
            $request = $parsedRequest;
        });

        $data = $this->createAdvancedPostRequest();
        $parser->feed($data);

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

    public function testHeaderEventWithShouldApplyDefaultAddressFromConstructor()
    {
        $request = null;

        $parser = new RequestHeaderParser('http://127.1.1.1:8000');
        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $parser->feed("GET /foo HTTP/1.0\r\n\r\n");

        $this->assertEquals('http://127.1.1.1:8000/foo', $request->getUri());
        $this->assertEquals('127.1.1.1:8000', $request->getHeaderLine('Host'));
    }

    public function testHeaderEventViaHttpsShouldApplySchemeFromConstructor()
    {
        $request = null;

        $parser = new RequestHeaderParser('https://127.1.1.1:8000');
        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $parser->feed("GET /foo HTTP/1.0\r\nHost: example.com\r\n\r\n");

        $this->assertEquals('https://example.com/foo', $request->getUri());
        $this->assertEquals('example.com', $request->getHeaderLine('Host'));
    }

    public function testHeaderOverflowShouldEmitError()
    {
        $error = null;
        $passedParser = null;

        $parser = new RequestHeaderParser();
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message, $parser) use (&$error, &$passedParser) {
            $error = $message;
            $passedParser = $parser;
        });

        $this->assertSame(1, count($parser->listeners('headers')));
        $this->assertSame(1, count($parser->listeners('error')));

        $data = str_repeat('A', 8193);
        $parser->feed($data);

        $this->assertInstanceOf('OverflowException', $error);
        $this->assertSame('Maximum header size of 8192 exceeded.', $error->getMessage());
        $this->assertSame($parser, $passedParser);
        $this->assertSame(0, count($parser->listeners('headers')));
        $this->assertSame(0, count($parser->listeners('error')));
    }

    public function testHeaderOverflowShouldNotEmitErrorWhenDataExceedsMaxHeaderSize()
    {
        $request = null;
        $bodyBuffer = null;

        $parser = new RequestHeaderParser();
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$request, &$bodyBuffer) {
            $request = $parsedRequest;
            $bodyBuffer = $parsedBodyBuffer;
        });

        $data = $this->createAdvancedPostRequest();
        $body = str_repeat('A', 8193 - strlen($data));
        $data .= $body;

        $parser->feed($data);

        $headers = array(
            'Host' => array('example.com'),
            'User-Agent' => array('react/alpha'),
            'Connection' => array('close'),
        );
        $this->assertSame($headers, $request->getHeaders());

        $this->assertSame($body, $bodyBuffer);
    }

    public function testInvalidEmptyRequestHeadersParseException()
    {
        $error = null;

        $parser = new RequestHeaderParser();
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $this->assertSame(1, count($parser->listeners('headers')));
        $this->assertSame(1, count($parser->listeners('error')));

        $parser->feed("\r\n\r\n");

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Unable to parse invalid request-line', $error->getMessage());
        $this->assertSame(0, count($parser->listeners('headers')));
        $this->assertSame(0, count($parser->listeners('error')));
    }

    public function testInvalidMalformedRequestLineParseException()
    {
        $error = null;

        $parser = new RequestHeaderParser();
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $this->assertSame(1, count($parser->listeners('headers')));
        $this->assertSame(1, count($parser->listeners('error')));

        $parser->feed("GET /\r\n\r\n");

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Unable to parse invalid request-line', $error->getMessage());
        $this->assertSame(0, count($parser->listeners('headers')));
        $this->assertSame(0, count($parser->listeners('error')));
    }

    public function testInvalidAbsoluteFormSchemeEmitsError()
    {
        $error = null;

        $parser = new RequestHeaderParser();
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $parser->feed("GET tcp://example.com:80/ HTTP/1.0\r\n\r\n");

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Invalid absolute-form request-target', $error->getMessage());
    }

    public function testOriginFormWithSchemeSeparatorInParam()
    {
        $request = null;

        $parser = new RequestHeaderParser();
        $parser->on('error', $this->expectCallableNever());
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$request) {
            $request = $parsedRequest;
        });

        $parser->feed("GET /somepath?param=http://example.com HTTP/1.1\r\nHost: localhost\r\n\r\n");

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

        $parser = new RequestHeaderParser();
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $parser->feed("GET ://example.com:80/ HTTP/1.0\r\n\r\n");

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Invalid request string', $error->getMessage());
    }

    public function testInvalidAbsoluteFormWithFragmentEmitsError()
    {
        $error = null;

        $parser = new RequestHeaderParser();
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $parser->feed("GET http://example.com:80/#home HTTP/1.0\r\n\r\n");

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Invalid absolute-form request-target', $error->getMessage());
    }

    public function testInvalidHeaderContainsFullUri()
    {
        $error = null;

        $parser = new RequestHeaderParser();
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $parser->feed("GET / HTTP/1.1\r\nHost: http://user:pass@host/\r\n\r\n");

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Invalid Host header value', $error->getMessage());
    }

    public function testInvalidAbsoluteFormWithHostHeaderEmpty()
    {
        $error = null;

        $parser = new RequestHeaderParser();
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $parser->feed("GET http://example.com/ HTTP/1.1\r\nHost: \r\n\r\n");

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('Invalid Host header value', $error->getMessage());
    }

    public function testInvalidConnectRequestWithNonAuthorityForm()
    {
        $error = null;

        $parser = new RequestHeaderParser();
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $parser->feed("CONNECT http://example.com:8080/ HTTP/1.1\r\nHost: example.com:8080\r\n\r\n");

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame('CONNECT method MUST use authority-form request target', $error->getMessage());
    }

    public function testInvalidHttpVersion()
    {
        $error = null;

        $parser = new RequestHeaderParser();
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $parser->feed("GET / HTTP/1.2\r\n\r\n");

        $this->assertInstanceOf('InvalidArgumentException', $error);
        $this->assertSame(505, $error->getCode());
        $this->assertSame('Received request with invalid protocol version', $error->getMessage());
    }

    public function testServerParamsWillBeSetOnHttpsRequest()
    {
        $request = null;

        $parser = new RequestHeaderParser(
            'https://127.1.1.1:8000',
            'https://192.168.1.1:8001'
        );

        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $parser->feed("GET /foo HTTP/1.0\r\nHost: example.com\r\n\r\n");
        $serverParams = $request->getServerParams();

        $this->assertEquals('on', $serverParams['HTTPS']);
        $this->assertNotEmpty($serverParams['REQUEST_TIME']);
        $this->assertNotEmpty($serverParams['REQUEST_TIME_FLOAT']);

        $this->assertEquals('127.1.1.1', $serverParams['SERVER_ADDR']);
        $this->assertEquals('8000', $serverParams['SERVER_PORT']);

        $this->assertEquals('192.168.1.1', $serverParams['REMOTE_ADDR']);
        $this->assertEquals('8001', $serverParams['REMOTE_PORT']);
    }

    public function testServerParamsWillBeSetOnHttpRequest()
    {
        $request = null;

        $parser = new RequestHeaderParser(
            'http://127.1.1.1:8000',
            'http://192.168.1.1:8001'
        );

        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $parser->feed("GET /foo HTTP/1.0\r\nHost: example.com\r\n\r\n");
        $serverParams = $request->getServerParams();

        $this->assertArrayNotHasKey('HTTPS', $serverParams);
        $this->assertNotEmpty($serverParams['REQUEST_TIME']);
        $this->assertNotEmpty($serverParams['REQUEST_TIME_FLOAT']);

        $this->assertEquals('127.1.1.1', $serverParams['SERVER_ADDR']);
        $this->assertEquals('8000', $serverParams['SERVER_PORT']);

        $this->assertEquals('192.168.1.1', $serverParams['REMOTE_ADDR']);
        $this->assertEquals('8001', $serverParams['REMOTE_PORT']);
    }

    public function testServerParamsWillNotSetRemoteAddressForUnixDomainSockets()
    {
        $request = null;

        $parser = new RequestHeaderParser(
            'unix://./server.sock',
            null
        );

        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $parser->feed("GET /foo HTTP/1.0\r\nHost: example.com\r\n\r\n");
        $serverParams = $request->getServerParams();

        $this->assertArrayNotHasKey('HTTPS', $serverParams);
        $this->assertNotEmpty($serverParams['REQUEST_TIME']);
        $this->assertNotEmpty($serverParams['REQUEST_TIME_FLOAT']);

        $this->assertArrayNotHasKey('SERVER_ADDR', $serverParams);
        $this->assertArrayNotHasKey('SERVER_PORT', $serverParams);

        $this->assertArrayNotHasKey('REMOTE_ADDR', $serverParams);
        $this->assertArrayNotHasKey('REMOTE_PORT', $serverParams);
    }

    public function testServerParamsWontBeSetOnMissingUrls()
    {
        $request = null;

        $parser = new RequestHeaderParser();

        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $parser->feed("GET /foo HTTP/1.0\r\nHost: example.com\r\n\r\n");
        $serverParams = $request->getServerParams();

        $this->assertNotEmpty($serverParams['REQUEST_TIME']);
        $this->assertNotEmpty($serverParams['REQUEST_TIME_FLOAT']);

        $this->assertArrayNotHasKey('SERVER_ADDR', $serverParams);
        $this->assertArrayNotHasKey('SERVER_PORT', $serverParams);

        $this->assertArrayNotHasKey('REMOTE_ADDR', $serverParams);
        $this->assertArrayNotHasKey('REMOTE_PORT', $serverParams);
    }

    public function testQueryParmetersWillBeSet()
    {
        $request = null;

        $parser = new RequestHeaderParser();

        $parser->on('headers', function ($parsedRequest) use (&$request) {
            $request = $parsedRequest;
        });

        $parser->feed("GET /foo.php?hello=world&test=this HTTP/1.0\r\nHost: example.com\r\n\r\n");
        $queryParams = $request->getQueryParams();

        $this->assertEquals('world', $queryParams['hello']);
        $this->assertEquals('this', $queryParams['test']);
    }

    private function createGetRequest()
    {
        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "\r\n";

        return $data;
    }

    private function createAdvancedPostRequest()
    {
        $data = "POST /foo?bar=baz HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "User-Agent: react/alpha\r\n";
        $data .= "Connection: close\r\n";
        $data .= "\r\n";

        return $data;
    }
}
