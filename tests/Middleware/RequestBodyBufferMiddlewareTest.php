<?php

namespace React\Tests\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\HttpBodyStream;
use React\Http\Io\ServerRequest;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;
use RingCentral\Psr7\BufferStream;

final class RequestBodyBufferMiddlewareTest extends TestCase
{
    public function testBufferingResolvesWhenStreamEnds()
    {
        $stream = new ThroughStream();
        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            new HttpBodyStream($stream, 11)
        );

        $exposedRequest = null;
        $buffer = new RequestBodyBufferMiddleware(20);
        $buffer(
            $serverRequest,
            function (ServerRequestInterface $request) use (&$exposedRequest) {
                $exposedRequest = $request;
            }
        );

        $stream->write('hello');
        $stream->write('world');
        $stream->end('!');

        $this->assertSame('helloworld!', $exposedRequest->getBody()->getContents());
    }

    public function testAlreadyBufferedResolvesImmediately()
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
        $buffer = new RequestBodyBufferMiddleware();
        $buffer(
            $serverRequest,
            function (ServerRequestInterface $request) use (&$exposedRequest) {
                $exposedRequest = $request;
            }
        );

        $this->assertSame($body, $exposedRequest->getBody()->getContents());
    }

    public function testUnknownSizeReturnsError411()
    {
        $body = $this->getMockBuilder('Psr\Http\Message\StreamInterface')->getMock();
        $body->expects($this->once())->method('getSize')->willReturn(null);

        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            $body
        );

        $buffer = new RequestBodyBufferMiddleware();
        $response = $buffer(
            $serverRequest,
            function () {}
        );

        $this->assertSame(411, $response->getStatusCode());
    }

    public function testExcessiveSizeReturnsError413()
    {
        $stream = new BufferStream(2);
        $stream->write('aa');

        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            $stream
        );

        $buffer = new RequestBodyBufferMiddleware(1);
        $response = $buffer(
            $serverRequest,
            function () {}
        );

        $this->assertSame(413, $response->getStatusCode());
    }
}
