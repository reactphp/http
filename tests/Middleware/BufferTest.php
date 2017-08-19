<?php

namespace React\Tests\Http\Middleware;

use React\Http\Middleware\Buffer;
use React\Http\MiddlewareStack;
use React\Http\Response;
use React\Http\ServerRequest;
use React\Tests\Http\TestCase;
use RingCentral\Psr7\BufferStream;

final class BufferTest extends TestCase
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

        $exposeRequest = new ExposeRequest();

        $response = new Response();
        $stack = new MiddlewareStack($response, array($exposeRequest));

        $buffer = new Buffer();
        $buffer->process($serverRequest, $stack);

        $exposedRequest = $exposeRequest->getRequest();
        $this->assertSame($body, $exposedRequest->getBody()->getContents());
    }

    public function testToLargeBody()
    {
        $size = $this->iniMaxPostSize() + 1;
        $stream = new BufferStream($size);
        $stream->write(str_repeat('x', $size));
        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            $stream
        );

        $stack = $this
            ->getMockBuilder('React\Http\MiddlewareStackInterface')
            ->getMock();
        $stack
            ->expects($this->never())
            ->method('process')
            ->with($serverRequest);

        $buffer = new Buffer();
        $response = $buffer->process($serverRequest, $stack);

        $this->assertInstanceOf('React\Http\Response', $response);
        $this->assertSame(413, $response->getStatusCode());
        $this->assertSame('Request body exceeds allowed limit', (string)$response->getBody());
    }

    private function iniMaxPostSize()
    {
        $size = ini_get('post_max_size');
        $suffix = strtoupper(substr($size, -1));
        if ($suffix === 'K') {
            return substr($size, 0, -1) * 1024;
        }
        if ($suffix === 'M') {
            return substr($size, 0, -1) * 1024 * 1024;
        }
        if ($suffix === 'G') {
            return substr($size, 0, -1) * 1024  * 1024 * 1024;
        }

        return $size;
    }
}
