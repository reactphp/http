<?php

namespace React\Tests\Http\Client;

use React\Http\Client\RequestData;
use React\Tests\Http\TestCase;

class RequestDataTest extends TestCase
{
    /** @test */
    public function toStringReturnsHTTPRequestMessage()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');

        $expected = "GET / HTTP/1.0\r\n" .
            "Host: www.example.com\r\n" .
            "User-Agent: React/alpha\r\n" .
            "\r\n";

        $this->assertSame($expected, $requestData->__toString());
    }

    /** @test */
    public function toStringReturnsHTTPRequestMessageWithEmptyQueryString()
    {
        $requestData = new RequestData('GET', 'http://www.example.com/path?hello=world');

        $expected = "GET /path?hello=world HTTP/1.0\r\n" .
            "Host: www.example.com\r\n" .
            "User-Agent: React/alpha\r\n" .
            "\r\n";

        $this->assertSame($expected, $requestData->__toString());
    }

    /** @test */
    public function toStringReturnsHTTPRequestMessageWithZeroQueryStringAndRootPath()
    {
        $requestData = new RequestData('GET', 'http://www.example.com?0');

        $expected = "GET /?0 HTTP/1.0\r\n" .
            "Host: www.example.com\r\n" .
            "User-Agent: React/alpha\r\n" .
            "\r\n";

        $this->assertSame($expected, $requestData->__toString());
    }

    /** @test */
    public function toStringReturnsHTTPRequestMessageWithOptionsAbsoluteRequestForm()
    {
        $requestData = new RequestData('OPTIONS', 'http://www.example.com/');

        $expected = "OPTIONS / HTTP/1.0\r\n" .
            "Host: www.example.com\r\n" .
            "User-Agent: React/alpha\r\n" .
            "\r\n";

        $this->assertSame($expected, $requestData->__toString());
    }

    /** @test */
    public function toStringReturnsHTTPRequestMessageWithOptionsAsteriskRequestForm()
    {
        $requestData = new RequestData('OPTIONS', 'http://www.example.com');

        $expected = "OPTIONS * HTTP/1.0\r\n" .
            "Host: www.example.com\r\n" .
            "User-Agent: React/alpha\r\n" .
            "\r\n";

        $this->assertSame($expected, $requestData->__toString());
    }

    /** @test */
    public function toStringReturnsHTTPRequestMessageWithProtocolVersion()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $requestData->setProtocolVersion('1.1');

        $expected = "GET / HTTP/1.1\r\n" .
            "Host: www.example.com\r\n" .
            "User-Agent: React/alpha\r\n" .
            "Connection: close\r\n" .
            "\r\n";

        $this->assertSame($expected, $requestData->__toString());
    }

    /** @test */
    public function toStringReturnsHTTPRequestMessageWithHeaders()
    {
        $requestData = new RequestData('GET', 'http://www.example.com', array(
            'User-Agent' => array(),
            'Via' => array(
                'first',
                'second'
            )
        ));

        $expected = "GET / HTTP/1.0\r\n" .
            "Host: www.example.com\r\n" .
            "Via: first\r\n" .
            "Via: second\r\n" .
            "\r\n";

        $this->assertSame($expected, $requestData->__toString());
    }

    /** @test */
    public function toStringReturnsHTTPRequestMessageWithHeadersInCustomCase()
    {
        $requestData = new RequestData('GET', 'http://www.example.com', array(
            'user-agent' => 'Hello',
            'LAST' => 'World'
        ));

        $expected = "GET / HTTP/1.0\r\n" .
            "Host: www.example.com\r\n" .
            "user-agent: Hello\r\n" .
            "LAST: World\r\n" .
            "\r\n";

        $this->assertSame($expected, $requestData->__toString());
    }

    /** @test */
    public function toStringReturnsHTTPRequestMessageWithProtocolVersionThroughConstructor()
    {
        $requestData = new RequestData('GET', 'http://www.example.com', array(), '1.1');

        $expected = "GET / HTTP/1.1\r\n" .
            "Host: www.example.com\r\n" .
            "User-Agent: React/alpha\r\n" .
            "Connection: close\r\n" .
            "\r\n";

        $this->assertSame($expected, $requestData->__toString());
    }

    /** @test */
    public function toStringUsesUserPassFromURL()
    {
        $requestData = new RequestData('GET', 'http://john:dummy@www.example.com');

        $expected = "GET / HTTP/1.0\r\n" .
            "Host: www.example.com\r\n" .
            "User-Agent: React/alpha\r\n" .
            "Authorization: Basic am9objpkdW1teQ==\r\n" .
            "\r\n";

        $this->assertSame($expected, $requestData->__toString());
    }
}
