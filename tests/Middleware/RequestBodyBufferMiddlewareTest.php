<?php

namespace React\Tests\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Io\HttpBodyStream;
use React\Http\Io\ServerRequest;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;
use RingCentral\Psr7\BufferStream;
use Clue\React\Block;

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

    public function testExcessiveSizeImmediatelyReturnsError413ForKnownSize()
    {
        $loop = Factory::create();
        
        $stream = new ThroughStream();
        $stream->end('aa');
        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            new HttpBodyStream($stream, 2)
        );

        $buffer = new RequestBodyBufferMiddleware(1);
        $response = $buffer(
            $serverRequest,
            function (ServerRequestInterface $request) {
                return $request;
            }
        );

        $this->assertSame(413, $response->getStatusCode());
    }

    public function testExcessiveSizeReturnsError413()
    {
        $loop = Factory::create();

        $stream = new ThroughStream();
        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            new HttpBodyStream($stream, null)
        );

        $buffer = new RequestBodyBufferMiddleware(1);
        $promise = $buffer(
            $serverRequest,
            function (ServerRequestInterface $request) {
                return $request;
            }
        );

        $stream->end('aa');

        $exposedResponse = null;
        $promise->then(
            function($response) use (&$exposedResponse) {
                $exposedResponse = $response;
            },
            $this->expectCallableNever()
        );

        $this->assertSame(413, $exposedResponse->getStatusCode());

        Block\await($promise, $loop);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testBufferingErrorThrows()
    {
        $loop = Factory::create();
        
        $stream = new ThroughStream();
        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            new HttpBodyStream($stream, null)
        );

        $buffer = new RequestBodyBufferMiddleware(1);
        $promise = $buffer(
            $serverRequest,
            function (ServerRequestInterface $request) {
                return $request;
            }
        );

        $stream->emit('error', array(new \RuntimeException()));

        Block\await($promise, $loop);
    }
}
