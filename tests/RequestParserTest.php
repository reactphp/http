<?php

namespace React\Tests\Http;

use React\Http\RequestParser;
use React\Stream\ThroughStream;

class RequestParserTest extends TestCase
{
    public function testSplitShouldHappenOnDoubleCrlf()
    {
        $stream = new ThroughStream();
        $parser = new RequestParser($stream);
        $parser->on('headers', $this->expectCallableNever());

        $stream->write("GET / HTTP/1.1\r\n");
        $stream->write("Host: example.com:80\r\n");
        $stream->write("Connection: close\r\n");

        $parser->removeAllListeners();
        $parser->on('headers', $this->expectCallableOnce());

        $stream->write("\r\n");
    }

    public function testFeedInOneGo()
    {
        $stream = new ThroughStream();
        $parser = new RequestParser($stream);
        $parser->on('headers', $this->expectCallableOnce());

        $data = $this->createGetRequest();
        $stream->write($data);
    }

    public function testHeadersEventShouldReturnRequestAndBodyBuffer()
    {
        $request = null;
        $bodyBuffer = null;

        $stream = new ThroughStream();
        $parser = new RequestParser($stream);
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$request, &$bodyBuffer) {
            $request = $parsedRequest;
            $bodyBuffer = $parsedBodyBuffer;
        });

        $data = $this->createGetRequest('RANDOM DATA', 11);
        $stream->write($data);

        $this->assertInstanceOf('React\Http\Request', $request);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/', $request->getPath());
        $this->assertSame(array(), $request->getQuery());
        $this->assertSame('1.1', $request->getHttpVersion());
        $this->assertSame(
            array('Host' => 'example.com:80', 'Connection' => 'close', 'Content-Length' => '11'),
            $request->getHeaders()
        );

        $this->assertSame('RANDOM DATA', $bodyBuffer);
    }

    public function testHeadersEventShouldReturnBinaryBodyBuffer()
    {
        $bodyBuffer = null;

        $stream = new ThroughStream();
        $parser = new RequestParser($stream);
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$bodyBuffer) {
            $bodyBuffer = $parsedBodyBuffer;
        });

        $data = $this->createGetRequest("\0x01\0x02\0x03\0x04\0x05", strlen("\0x01\0x02\0x03\0x04\0x05"));
        $stream->write($data);

        $this->assertSame("\0x01\0x02\0x03\0x04\0x05", $bodyBuffer);
    }

    public function testHeadersEventShouldParsePathAndQueryString()
    {
        $request = null;

        $stream = new ThroughStream();
        $parser = new RequestParser($stream);
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$request) {
            $request = $parsedRequest;
        });

        $data = $this->createAdvancedPostRequest();
        $stream->write($data);

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

    public function testShouldNotReceiveBodyContent()
    {
        $content1 = "{\"test\":";
        $content2 = " \"value\"}";

        $request = null;
        $body = null;

        $stream = new ThroughStream();
        $parser = new RequestParser($stream);
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$request, &$body) {
            $request = $parsedRequest;
            $body = $parsedBodyBuffer;
        });

        $data = $this->createAdvancedPostRequest('', 17);
        $stream->write($data);
        $stream->write($content1);
        $stream->write($content2);

        $this->assertInstanceOf('React\Http\Request', $request);
        $this->assertSame($body, '');
    }

    public function testShouldReceiveBodyContentPartial()
    {
        $content1 = "{\"test\":";
        $content2 = " \"value\"}";

        $request = null;
        $body = null;

        $stream = new ThroughStream();
        $parser = new RequestParser($stream);
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$request, &$body) {
            $request = $parsedRequest;
            $body = $parsedBodyBuffer;
        });

        $data = $this->createAdvancedPostRequest('', 17);
        $stream->write($data . $content1);
        $stream->write($content2);

        $this->assertInstanceOf('React\Http\Request', $request);
        $this->assertSame($body, $content1);
    }

    public function testHeaderOverflowShouldEmitError()
    {
        $error = null;

        $stream = new ThroughStream();
        $parser = new RequestParser($stream);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $data = str_repeat('A', 4097);
        $stream->write($data);

        $this->assertInstanceOf('OverflowException', $error);
        $this->assertSame('Maximum header size of 4096 exceeded.', $error->getMessage());
    }

    public function testOnePassHeaderTooLarge()
    {
        $error = null;

        $stream = new ThroughStream();
        $parser = new RequestParser($stream);
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
                $error = $message;
            });

        $data  = "POST /foo?bar=baz HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Cookie: " . str_repeat('A', 4097) . "\r\n";
        $data .= "\r\n";
        $stream->write($data);

        $this->assertInstanceOf('OverflowException', $error);
        $this->assertSame('Maximum header size of 4096 exceeded.', $error->getMessage());
    }

    public function testBodyShouldNotOverflowHeader()
    {
        $error = null;

        $stream = new ThroughStream();
        $parser = new RequestParser($stream);
        $parser->on('headers', $this->expectCallableOnce());
        $parser->on('error', function ($message) use (&$error) {
                $error = $message;
            });

        $data = str_repeat('A', 4097);
        $stream->write($this->createAdvancedPostRequest() . $data);

        $this->assertNull($error);
    }

    private function createGetRequest($content = '', $len = 0)
    {
        $data  = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        if($len) {
            $data .= "Content-Length: $len\r\n";
        }
        $data .= "\r\n";
        $data .= $content;

        return $data;
    }

    private function createAdvancedPostRequest($content = '', $len = 0)
    {
        $data  = "POST /foo?bar=baz HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "User-Agent: react/alpha\r\n";
        $data .= "Connection: close\r\n";
        if($len) {
            $data .= "Content-Length: $len\r\n";
        }
        $data .= "\r\n";
        $data .= $content;

        return $data;
    }
}
