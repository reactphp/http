<?php

namespace React\Tests\Http;

use React\Http\ServerRequest;

class ServerRequestTest extends TestCase
{
    private $request;

    public function setUp()
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
        $this->assertEquals(null, $request->getAttribute('hi', null));
    }

    public function testWithoutAttribute()
    {
        $request = $this->request->withAttribute('hello', 'world');
        $request = $request->withAttribute('test', 'nice');

        $request = $request->withoutAttribute('hello');

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('test' => 'nice'), $request->getAttributes());
    }

    public function testWithCookieParams()
    {
        $request = $this->request->withCookieParams(array('test' => 'world'));

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('test' => 'world'), $request->getCookieParams());
    }

    public function testWithQueryParams()
    {
        $request = $this->request->withQueryParams(array('test' => 'world'));

        $this->assertNotSame($request, $this->request);
        $this->assertEquals(array('test' => 'world'), $request->getQueryParams());
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
}
