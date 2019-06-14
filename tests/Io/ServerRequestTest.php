<?php

namespace React\Tests\Http\Io;

use React\Http\Io\ServerRequest;
use React\Tests\Http\TestCase;

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

    public function testParseSingleCookieNameValuePairWillReturnValidArray()
    {
        $this->request = new ServerRequest(
            'GET',
            'http://localhost',
            array('Cookie' => 'hello=world')
        );

        $cookies = $this->request->getCookieParams();
        $this->assertEquals(array('hello' => 'world'), $cookies);
    }

    public function testParseMultipleCookieNameValuePairWillReturnValidArray()
    {
        $this->request = new ServerRequest(
            'GET',
            'http://localhost',
            array('Cookie' => 'hello=world; test=abc')
        );

        $cookies = $this->request->getCookieParams();
        $this->assertEquals(array('hello' => 'world', 'test' => 'abc'), $cookies);
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

    public function testMultipleCookiesWithSameNameWillReturnLastValue()
    {
        $this->request = new ServerRequest(
            'GET',
            'http://localhost',
            array('Cookie' => 'hello=world; hello=abc')
        );

        $cookies = $this->request->getCookieParams();
        $this->assertEquals(array('hello' => 'abc'), $cookies);
    }

    public function testOtherEqualSignsWillBeAddedToValueAndWillReturnValidArray()
    {
        $this->request = new ServerRequest(
            'GET',
            'http://localhost',
            array('Cookie' => 'hello=world=test=php')
        );

        $cookies = $this->request->getCookieParams();
        $this->assertEquals(array('hello' => 'world=test=php'), $cookies);
    }

    public function testSingleCookieValueInCookiesReturnsEmptyArray()
    {
        $this->request = new ServerRequest(
            'GET',
            'http://localhost',
            array('Cookie' => 'world')
        );

        $cookies = $this->request->getCookieParams();
        $this->assertEquals(array(), $cookies);
    }

    public function testSingleMutlipleCookieValuesReturnsEmptyArray()
    {
        $this->request = new ServerRequest(
            'GET',
            'http://localhost',
            array('Cookie' => 'world; test')
        );

        $cookies = $this->request->getCookieParams();
        $this->assertEquals(array(), $cookies);
    }

    public function testSingleValueIsValidInMultipleValueCookieWillReturnValidArray()
    {
        $this->request = new ServerRequest(
            'GET',
            'http://localhost',
            array('Cookie' => 'world; test=php')
        );

        $cookies = $this->request->getCookieParams();
        $this->assertEquals(array('test' => 'php'), $cookies);
    }

    public function testUrlEncodingForValueWillReturnValidArray()
    {
        $this->request = new ServerRequest(
            'GET',
            'http://localhost',
            array('Cookie' => 'hello=world%21; test=100%25%20coverage')
        );

        $cookies = $this->request->getCookieParams();
        $this->assertEquals(array('hello' => 'world!', 'test' => '100% coverage'), $cookies);
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
}
