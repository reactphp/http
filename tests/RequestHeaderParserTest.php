<?php

namespace React\Tests\Http;

use React\Http\RequestHeaderParser;

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

        $this->assertInstanceOf('React\Http\Request', $request);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/', $request->getPath());
        $this->assertSame(array(), $request->getQuery());
        $this->assertSame('1.1', $request->getHttpVersion());
        $this->assertSame(array('Host' => 'example.com:80', 'Connection' => 'close'), $request->getHeaders());

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

        $this->assertInstanceOf('React\Http\Request', $request);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/foo', $request->getPath());
        $this->assertSame(array('bar' => 'baz'), $request->getQuery());
        $this->assertSame('1.1', $request->getHttpVersion());
        $headers = array(
            'Host' => 'example.com:80',
            'User-Agent' => 'react/alpha',
            'Connection' => 'close',
        );
        $this->assertSame($headers, $request->getHeaders());
    }

    /**
     * @param RequestHeaderParser $parser
     * @param string              $data
     */
    private function checkHeaderOverflowShouldEmitError(
        RequestHeaderParser $parser,
        $data
    ) {
        $error = $passedParser = null;

        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message, $parser) use (&$error, &$passedParser) {
            $error = $message;
            $passedParser = $parser;
        });

        $this->assertSame(1, count($parser->listeners('headers')));
        $this->assertSame(1, count($parser->listeners('error')));

        $parser->feed($data);

        $this->assertInstanceOf('OverflowException', $error);
        $this->assertSame(
            sprintf('Maximum header size of %d exceeded.', $parser->getMaxHeadersSize()),
            $error->getMessage()
        );
        $this->assertSame($parser, $passedParser);
        $this->assertSame(0, count($parser->listeners('headers')));
        $this->assertSame(0, count($parser->listeners('error')));
    }

    public function testHeaderOverflowShouldEmitErrorDefault()
    {
        /*
         * This checks that exception is thrown if buffer exceeds header
         * max length, but header end is still missing
         */
        $data = str_repeat('A', RequestHeaderParser::DEFAULT_HEADER_SIZE + 1);
        $this->checkHeaderOverflowShouldEmitError(new RequestHeaderParser(), $data);

        /*
         * This checks that exception is thrown if header size really exceeded
         */
        $data = str_pad($data . "\r\n\r\n", RequestHeaderParser::DEFAULT_HEADER_SIZE * 2, 'B');
        $this->checkHeaderOverflowShouldEmitError(new RequestHeaderParser(), $data);

        $data = str_repeat('A', 8096 + 1);
        $this->checkHeaderOverflowShouldEmitError(new RequestHeaderParser(8096), $data);
    }

    public function testGuzzleRequestParseException()
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
        $this->assertSame('Invalid message', $error->getMessage());
        $this->assertSame(0, count($parser->listeners('headers')));
        $this->assertSame(0, count($parser->listeners('error')));
    }

    /**
     * Checks if HeaderParser supports data chunks of size
     * bigger, than @see RequestHeaderParser::$maxSize option
     *
     * @url https://github.com/reactphp/http/issues/80
     */
    public function testHugeBodyFirstChunk()
    {
        $bodyBuffer = null;

        $parser = new RequestHeaderParser();
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$bodyBuffer) {
            $bodyBuffer = $parsedBodyBuffer;
        });
        $parser->on('error', $this->expectCallableNever());

        $data = $this->createGetRequest();
        $headLength = strlen($data);
        $totalLength = 4096 * 16;
        $data = str_pad($data, $totalLength, "\0x00");
        $parser->feed($data);

        $this->assertEquals($totalLength - $headLength, strlen($bodyBuffer));
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
