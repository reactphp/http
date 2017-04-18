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

    public function testParseSingleCookieNameValuePairWillReturnValidArray()
    {
        $cookieString = 'hello=world';
        $cookies = ServerRequest::parseCookie($cookieString);
        $this->assertEquals(array('hello' => 'world'), $cookies);
    }

    public function testParseMultipleCookieNameValuePaiWillReturnValidArray()
    {
        $cookieString = 'hello=world;test=abc';
        $cookies = ServerRequest::parseCookie($cookieString);
        $this->assertEquals(array('hello' => 'world', 'test' => 'abc'), $cookies);
    }

    public function testParseMultipleCookieNameValuePairWillReturnFalse()
    {
        // Could be done through multiple 'Cookie' headers
        // getHeaderLine('Cookie') will return a value seperated by coma
        // e.g.
        // GET / HTTP/1.1\r\n
        // Host: test.org\r\n
        // Cookie: hello=world\r\n
        // Cookie: test=abc\r\n\r\n
        $cookieString = 'hello=world,test=abc';
        $cookies = ServerRequest::parseCookie($cookieString);
        $this->assertEquals(false, $cookies);
    }

    public function testOnlyFirstSetWillBeAddedToCookiesArray()
    {
        $cookieString = 'hello=world;hello=abc';
        $cookies = ServerRequest::parseCookie($cookieString);
        $this->assertEquals(array('hello' => 'abc'), $cookies);
    }

    public function testOtherEqualSignsWillBeAddedToValueAndWillReturnValidArray()
    {
        $cookieString = 'hello=world=test=php';
        $cookies = ServerRequest::parseCookie($cookieString);
        $this->assertEquals(array('hello' => 'world=test=php'), $cookies);
    }

    public function testSingleCookieValueInCookiesReturnsEmptyArray()
    {
        $cookieString = 'world';
        $cookies = ServerRequest::parseCookie($cookieString);
        $this->assertEquals(array(), $cookies);
    }

    public function testSingleMutlipleCookieValuesReturnsEmptyArray()
    {
        $cookieString = 'world;test';
        $cookies = ServerRequest::parseCookie($cookieString);
        $this->assertEquals(array(), $cookies);
    }

    public function testSingleValueIsValidInMultipleValueCookieWillReturnValidArray()
    {
        $cookieString = 'world;test=php';
        $cookies = ServerRequest::parseCookie($cookieString);
        $this->assertEquals(array('test' => 'php'), $cookies);
    }

    public function testUrlEncodingForValueWillReturnValidArray()
    {
        $cookieString = 'hello=world%21;test=100%25%20coverage';
        $cookies = ServerRequest::parseCookie($cookieString);
        $this->assertEquals(array('hello' => 'world!', 'test' => '100% coverage'), $cookies);
    }

    public function testUrlEncodingForKeyWillReturnValidArray()
    {
        $cookieString = 'react%3Bphp=is%20great';
        $cookies = ServerRequest::parseCookie($cookieString);
        $this->assertEquals(array('react;php' => 'is great'), $cookies);
    }
}
