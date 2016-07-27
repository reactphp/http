<?php

namespace React\Tests\Http;

use React\Http\RequestParser;

class RequestParserTest extends TestCase
{
    public function testSplitShouldHappenOnDoubleCrlf()
    {
        $parser = new RequestParser();
        $parser->on('headers', $this->expectCallableNever());

        $parser->feed("GET / HTTP/1.1\r\n");
        $parser->feed("Host: example.com:80\r\n");
        $parser->feed("Connection: close\r\n");

        $parser->removeAllListeners();
        $parser->on('headers', $this->expectCallableOnce());

        $parser->feed("\r\n");
    }
    
    public function testEmptyHeaderShouldEmitError()
    {
        $parser = new RequestParser();
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', $this->expectCallableOnce());

        $parser->feed("\r\n\r\n");
    }

    public function testInvalidHeaderShouldEmitError()
    {
        $parser = new RequestParser();
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', $this->expectCallableOnce());

        $parser->feed("GET\r\nhello: there\r\n\r\n");
    }

    public function testFeedInOneGo()
    {
        $parser = new RequestParser();
        $parser->on('headers', $this->expectCallableOnce());

        $data = $this->createGetRequest();
        $parser->feed($data);
    }

    public function testHeadersEventShouldReturnRequestAndBodyBuffer()
    {
        $request = null;
        $bodyBuffer = null;

        $parser = new RequestParser();
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$request, &$bodyBuffer) {
            $request = $parsedRequest;
            $bodyBuffer = $parsedBodyBuffer;
        });

        $data = $this->createGetRequest('RANDOM DATA', 11);
        $parser->feed($data);

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

        $parser = new RequestParser();
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$bodyBuffer) {
            $bodyBuffer = $parsedBodyBuffer;
        });

        $data = $this->createGetRequest("\0x01\0x02\0x03\0x04\0x05", strlen("\0x01\0x02\0x03\0x04\0x05"));
        $parser->feed($data);

        $this->assertSame("\0x01\0x02\0x03\0x04\0x05", $bodyBuffer);
    }

    public function testHeadersEventShouldParsePathAndQueryString()
    {
        $request = null;

        $parser = new RequestParser();
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

    public function testShouldReceiveBodyContent()
    {
        $content1 = "{\"test\":"; $content2 = " \"value\"}";

        $request = null;
        $body = null;

        $parser = new RequestParser();
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$request, &$body) {
            $request = $parsedRequest;
            $body = $parsedBodyBuffer;
        });

        $data = $this->createAdvancedPostRequest('', 17);
        $parser->feed($data);
        $parser->feed($content1);
        $parser->feed($content2 . "\r\n");

        $this->assertInstanceOf('React\Http\Request', $request);
        $this->assertEquals($content1 . $content2, $request->getBody());
        $this->assertSame($body, $request->getBody());
    }

    public function testShouldReceiveMultiPartBody()
    {

        $request = null;
        $body = null;

        $parser = new RequestParser();
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$request, &$body) {
            $request = $parsedRequest;
            $body = $parsedBodyBuffer;
        });

        $parser->feed($this->createMultipartRequest());

        $this->assertInstanceOf('React\Http\Request', $request);
        $this->assertEquals(
            $request->getPost(),
            ['user' => 'single', 'user2' => 'second', 'users' => ['first in array', 'second in array']]
        );
        $this->assertEquals(2, count($request->getFiles()));
        $this->assertEquals(2, count($request->getFiles()['files']));
    }

    public function testShouldReceivePostInBody()
    {
        $request = null;
        $body = null;

        $parser = new RequestParser();
        $parser->on('headers', function ($parsedRequest, $parsedBodyBuffer) use (&$request, &$body) {
                $request = $parsedRequest;
                $body = $parsedBodyBuffer;
            });

        $parser->feed($this->createPostWithContent());

        $this->assertInstanceOf('React\Http\Request', $request);
        $this->assertSame('', $body);
        $this->assertEquals(
            $request->getPost(),
            ['user' => 'single', 'user2' => 'second', 'users' => ['first in array', 'second in array']]
        );
    }

    public function testHeaderOverflowShouldEmitError()
    {
        $error = null;

        $parser = new RequestParser();
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $data = str_repeat('A', 4097);
        $parser->feed($data);

        $this->assertInstanceOf('OverflowException', $error);
        $this->assertSame('Maximum header size of 4096 exceeded.', $error->getMessage());
    }

    public function testOnePassHeaderTooLarge()
    {
        $error = null;

        $parser = new RequestParser();
        $parser->on('headers', $this->expectCallableNever());
        $parser->on('error', function ($message) use (&$error) {
                $error = $message;
            });

        $data  = "POST /foo?bar=baz HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Cookie: " . str_repeat('A', 4097) . "\r\n";
        $data .= "\r\n";
        $parser->feed($data);

        $this->assertInstanceOf('OverflowException', $error);
        $this->assertSame('Maximum header size of 4096 exceeded.', $error->getMessage());
    }

    public function testBodyShouldNotOverflowHeader()
    {
        $error = null;

        $parser = new RequestParser();
        $parser->on('headers', $this->expectCallableOnce());
        $parser->on('error', function ($message) use (&$error) {
                $error = $message;
            });

        $data = str_repeat('A', 4097);
        $parser->feed($this->createAdvancedPostRequest() . $data);

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

    private function createPostWithContent()
    {
        $data  = "POST /foo?bar=baz HTTP/1.1\r\n";
        $data .= "Host: localhost:8080\r\n";
        $data .= "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:32.0) Gecko/20100101 Firefox/32.0\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $data .= "Content-Length: 79\r\n";
        $data .= "\r\n";
        $data .= "user=single&user2=second&users%5B%5D=first+in+array&users%5B%5D=second+in+array\r\n";

        return $data;
    }

    private function createMultipartRequest()
    {
        $data  = "POST / HTTP/1.1\r\n";
        $data .= "Host: localhost:8080\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Type: multipart/form-data; boundary=---------------------------12758086162038677464950549563\r\n";
        $data .= "Content-Length: 1097\r\n";
        $data .= "\r\n";

        $data .= "-----------------------------12758086162038677464950549563\r\n";
        $data .= "Content-Disposition: form-data; name=\"user\"\r\n";
        $data .= "\r\n";
        $data .= "single\r\n";
        $data .= "-----------------------------12758086162038677464950549563\r\n";
        $data .= "Content-Disposition: form-data; name=\"user2\"\r\n";
        $data .= "\r\n";
        $data .= "second\r\n";
        $data .= "-----------------------------12758086162038677464950549563\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[]\"\r\n";
        $data .= "\r\n";
        $data .= "first in array\r\n";
        $data .= "-----------------------------12758086162038677464950549563\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[]\"\r\n";
        $data .= "\r\n";
        $data .= "second in array\r\n";
        $data .= "-----------------------------12758086162038677464950549563\r\n";
        $data .= "Content-Disposition: form-data; name=\"file\"; filename=\"User.php\"\r\n";
        $data .= "Content-Type: text/php\r\n";
        $data .= "\r\n";
        $data .= "<?php echo 'User';\r\n";
        $data .= "\r\n";
        $data .= "-----------------------------12758086162038677464950549563\r\n";
        $data .= "Content-Disposition: form-data; name=\"files[]\"; filename=\"blank.gif\"\r\n";
        $data .= "Content-Type: image/gif\r\n";
        $data .= "\r\n";
        $data .= base64_decode("R0lGODlhAQABAIAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==") . "\r\n";
        $data .= "-----------------------------12758086162038677464950549563\r\n";
        $data .= "Content-Disposition: form-data; name=\"files[]\"; filename=\"User.php\"\r\n";
        $data .= "Content-Type: text/php\r\n";
        $data .= "\r\n";
        $data .= "<?php echo 'User';\r\n";
        $data .= "\r\n";
        $data .= "-----------------------------12758086162038677464950549563--\r\n";

        return $data;
    }
}
