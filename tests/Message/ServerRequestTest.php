<?php

namespace React\Tests\Http\Message;

use React\Http\Io\HttpBodyStream;
use React\Http\Message\ServerRequest;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;

class ServerRequestTest extends TestCase
{
    private $request;

    /**
     * @before
     */
    public function setUpRequest()
    {
        $this->request = new ServerRequest('GET', 'http://localhost');
    }

    public function testGetNoAttributes()
    {
        $this->assertEquals(array(), $this->request->getAttributes());
    }

    public function testWithAttribute()
    {
        $request = $this->request->withAttribute('hello', 'world');

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('hello' => 'world'), $request->getAttributes());
    }

    public function testGetAttribute()
    {
        $request = $this->request->withAttribute('hello', 'world');

        $this->assertNotSame($request, $this->request);
        $this->assertEquals('world', $request->getAttribute('hello'));
    }

    public function testGetDefaultAttribute()
    {
        $request = $this->request->withAttribute('hello', 'world');

        $this->assertNotSame($request, $this->request);
        $this->assertNull($request->getAttribute('hi', null));
    }

    public function testWithoutAttribute()
    {
        $request = $this->request->withAttribute('hello', 'world');
        $request = $request->withAttribute('test', 'nice');

        $request = $request->withoutAttribute('hello');

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('test' => 'nice'), $request->getAttributes());
    }

    public function testGetQueryParamsFromConstructorUri()
    {
        $this->request = new ServerRequest('GET', 'http://localhost/?test=world');

        $this->assertEquals(array('test' => 'world'), $this->request->getQueryParams());
    }

    public function testWithCookieParams()
    {
        $request = $this->request->withCookieParams(array('test' => 'world'));

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('test' => 'world'), $request->getCookieParams());
    }

    public function testGetQueryParamsFromConstructorUriUrlencoded()
    {
        $this->request = new ServerRequest('GET', 'http://localhost/?test=hello+world%21');

        $this->assertEquals(array('test' => 'hello world!'), $this->request->getQueryParams());
    }

    public function testWithQueryParams()
    {
        $request = $this->request->withQueryParams(array('test' => 'world'));

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('test' => 'world'), $request->getQueryParams());
    }

    public function testWithQueryParamsWithoutSpecialEncoding()
    {
        $request = $this->request->withQueryParams(array('test' => 'hello world!'));

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('test' => 'hello world!'), $request->getQueryParams());
    }

    public function testWithUploadedFiles()
    {
        $request = $this->request->withUploadedFiles(array('test' => 'world'));

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('test' => 'world'), $request->getUploadedFiles());
    }

    public function testWithParsedBody()
    {
        $request = $this->request->withParsedBody(array('test' => 'world'));

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('test' => 'world'), $request->getParsedBody());
    }

    public function testServerRequestParameter()
    {
        $body = 'hello=world';
        $request = new ServerRequest(
            'POST',
            'http://127.0.0.1',
            array('Content-Length' => strlen($body)),
            $body,
            '1.0',
            array('SERVER_ADDR' => '127.0.0.1')
        );

        $serverParams = $request->getServerParams();
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('http://127.0.0.1', $request->getUri());
        $this->assertEquals('11', $request->getHeaderLine('Content-Length'));
        $this->assertEquals('hello=world', $request->getBody());
        $this->assertEquals('1.0', $request->getProtocolVersion());
        $this->assertEquals('127.0.0.1', $serverParams['SERVER_ADDR']);
    }

    public function testParseMultipleCookieHeadersAreNotAllowedAndWillReturnEmptyArray()
    {
        $this->request = new ServerRequest(
            'GET',
            'http://localhost',
            array('Cookie' => array('hello=world', 'test=abc'))
        );

        $cookies = $this->request->getCookieParams();
        $this->assertEquals(array(), $cookies);
    }

    public function testUrlEncodingForKeyWillReturnValidArray()
    {
        $this->request = new ServerRequest(
            'GET',
            'http://localhost',
            array('Cookie' => 'react%3Bphp=is%20great')
        );

        $cookies = $this->request->getCookieParams();
        $this->assertEquals(array('react;php' => 'is great'), $cookies);
    }

    public function testCookieWithoutSpaceAfterSeparatorWillBeAccepted()
    {
        $this->request = new ServerRequest(
            'GET',
            'http://localhost',
            array('Cookie' => 'hello=world;react=php')
        );

        $cookies = $this->request->getCookieParams();
        $this->assertEquals(array('hello' => 'world', 'react' => 'php'), $cookies);
    }

    public function testConstructWithStringRequestBodyReturnsStringBodyWithAutomaticSize()
    {
        $request = new ServerRequest(
            'GET',
            'http://localhost',
            array(),
            'foo'
        );

        $body = $request->getBody();
        $this->assertSame(3, $body->getSize());
        $this->assertEquals('foo', (string) $body);
    }

    public function testConstructWithHttpBodyStreamReturnsBodyAsIs()
    {
        $request = new ServerRequest(
            'GET',
            'http://localhost',
            array(),
            $body = new HttpBodyStream(new ThroughStream(), 100)
        );

        $this->assertSame($body, $request->getBody());
    }

    public function testConstructWithStreamingRequestBodyReturnsBodyWhichImplementsReadableStreamInterfaceWithSizeZeroDefault()
    {
        $request = new ServerRequest(
            'GET',
            'http://localhost',
            array(),
            new ThroughStream()
        );

        $body = $request->getBody();
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);
        $this->assertSame(0, $body->getSize());
    }

    public function testConstructWithStreamingRequestBodyReturnsBodyWithSizeFromContentLengthHeader()
    {
        $request = new ServerRequest(
            'GET',
            'http://localhost',
            array(
                'Content-Length' => 100
            ),
            new ThroughStream()
        );

        $body = $request->getBody();
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);
        $this->assertSame(100, $body->getSize());
    }

    public function testConstructWithStreamingRequestBodyReturnsBodyWithSizeUnknownForTransferEncodingChunked()
    {
        $request = new ServerRequest(
            'GET',
            'http://localhost',
            array(
                'Transfer-Encoding' => 'Chunked'
            ),
            new ThroughStream()
        );

        $body = $request->getBody();
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);
        $this->assertNull($body->getSize());
    }

    public function testConstructWithFloatRequestBodyThrows()
    {
        $this->setExpectedException('InvalidArgumentException');
        new ServerRequest(
            'GET',
            'http://localhost',
            array(),
            1.0
        );
    }

    public function testConstructWithResourceRequestBodyThrows()
    {
        $this->setExpectedException('InvalidArgumentException');
        new ServerRequest(
            'GET',
            'http://localhost',
            array(),
            tmpfile()
        );
    }
}
