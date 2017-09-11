<?php

namespace React\Tests\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Middleware\RequestBodyBuffer;
use React\Http\ServerRequest;
use React\Tests\Http\TestCase;
use RingCentral\Psr7\BufferStream;

final class RequestBodyBufferMiddlewareTest extends TestCase
{
    public function testBuffer()
    {
        $size = 1024;
        $body = str_repeat('x', $size);
        $stream = new BufferStream($size);
        $stream->write($body);
        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            $stream
        );

        $exposedRequest = null;
        $buffer = new RequestBodyBuffer();
        $buffer(
            $serverRequest,
            function (ServerRequestInterface $request) use (&$exposedRequest) {
                $exposedRequest = $request;
            }
        );

        $this->assertSame($body, $exposedRequest->getBody()->getContents());
    }

    public function test411Error()
    {
        $body = $this->getMockBuilder('Psr\Http\Message\StreamInterface')->getMock();
        $body->expects($this->once())->method('getSize')->willReturn(null);

        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            $body
        );

        $buffer = new RequestBodyBuffer();
        $response = $buffer(
            $serverRequest,
            function () {}
        );

        $this->assertSame(411, $response->getStatusCode());
    }

    public function test413Error()
    {
        $stream = new BufferStream(2);
        $stream->write('aa');

        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            $stream
        );

        $buffer = new RequestBodyBuffer(1);
        $response = $buffer(
            $serverRequest,
            function () {}
        );

        $this->assertSame(413, $response->getStatusCode());
    }
}
