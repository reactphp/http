<?php

namespace React\Tests\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\Io\HttpBodyStream;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
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

        $this->assertSame(11, $exposedRequest->getBody()->getSize());
        $this->assertSame('helloworld!', $exposedRequest->getBody()->getContents());
    }

    public function testAlreadyBufferedResolvesImmediately()
    {
        $size = 1024;
        $body = str_repeat('x', $size);
        $stream = new BufferStream(1024);
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

        $this->assertSame($size, $exposedRequest->getBody()->getSize());
        $this->assertSame($body, $exposedRequest->getBody()->getContents());
    }

    public function testEmptyStreamingResolvesImmediatelyWithEmptyBufferedBody()
    {
        $stream = new ThroughStream();
        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            $body = new HttpBodyStream($stream, 0)
        );

        $exposedRequest = null;
        $buffer = new RequestBodyBufferMiddleware();
        $buffer(
            $serverRequest,
            function (ServerRequestInterface $request) use (&$exposedRequest) {
                $exposedRequest = $request;
            }
        );

        $this->assertSame(0, $exposedRequest->getBody()->getSize());
        $this->assertSame('', $exposedRequest->getBody()->getContents());
        $this->assertNotSame($body, $exposedRequest->getBody());
    }

    public function testEmptyBufferedResolvesImmediatelyWithSameBody()
    {
        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            ''
        );
        $body = $serverRequest->getBody();

        $exposedRequest = null;
        $buffer = new RequestBodyBufferMiddleware();
        $buffer(
            $serverRequest,
            function (ServerRequestInterface $request) use (&$exposedRequest) {
                $exposedRequest = $request;
            }
        );

        $this->assertSame(0, $exposedRequest->getBody()->getSize());
        $this->assertSame('', $exposedRequest->getBody()->getContents());
        $this->assertSame($body, $exposedRequest->getBody());
    }

    public function testClosedStreamResolvesImmediatelyWithEmptyBody()
    {
        $stream = new ThroughStream();
        $stream->close();

        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            new HttpBodyStream($stream, 2)
        );

        $exposedRequest = null;
        $buffer = new RequestBodyBufferMiddleware(1);
        $buffer(
            $serverRequest,
            function (ServerRequestInterface $request) use (&$exposedRequest) {
                $exposedRequest = $request;
            }
        );

        $this->assertSame(0, $exposedRequest->getBody()->getSize());
        $this->assertSame('', $exposedRequest->getBody()->getContents());
    }

    public function testKnownExcessiveSizedBodyIsDiscardedAndRequestIsPassedDownToTheNextMiddleware()
    {
        $stream = new ThroughStream();
        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            new HttpBodyStream($stream, 2)
        );

        $buffer = new RequestBodyBufferMiddleware(1);

        $promise = $buffer(
            $serverRequest,
            function (ServerRequestInterface $request) {
                return new Response(200, array(), $request->getBody()->getContents());
            }
        );

        $stream->end('aa');

        $response = \React\Async\await($promise);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getBody()->getContents());
    }

    public function testKnownExcessiveSizedWithIniLikeSize()
    {
        $stream = new ThroughStream();
        Loop::addTimer(0.001, function () use ($stream) {
            $stream->end(str_repeat('a', 2048));
        });
        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            new HttpBodyStream($stream, 2048)
        );

        $buffer = new RequestBodyBufferMiddleware('1K');
        $response = \React\Async\await($buffer(
            $serverRequest,
            function (ServerRequestInterface $request) {
                return new Response(200, array(), $request->getBody()->getContents());
            }
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getBody()->getContents());
    }

    public function testAlreadyBufferedExceedingSizeResolvesImmediatelyWithEmptyBody()
    {
        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            'hello'
        );

        $exposedRequest = null;
        $buffer = new RequestBodyBufferMiddleware(1);
        $buffer(
            $serverRequest,
            function (ServerRequestInterface $request) use (&$exposedRequest) {
                $exposedRequest = $request;
            }
        );

        $this->assertSame(0, $exposedRequest->getBody()->getSize());
        $this->assertSame('', $exposedRequest->getBody()->getContents());
    }

    public function testExcessiveSizeBodyIsDiscardedAndTheRequestIsPassedDownToTheNextMiddleware()
    {
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
                return new Response(200, array(), $request->getBody()->getContents());
            }
        );

        $stream->end('aa');

        $exposedResponse = \React\Async\await($promise->then(
            null,
            $this->expectCallableNever()
        ));

        $this->assertSame(200, $exposedResponse->getStatusCode());
        $this->assertSame('', $exposedResponse->getBody()->getContents());
    }

    public function testBufferingRejectsWhenNextHandlerThrowsWhenStreamEnds()
    {
        $stream = new ThroughStream();

        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            new HttpBodyStream($stream, null)
        );

        $buffer = new RequestBodyBufferMiddleware(100);
        $promise = $buffer(
            $serverRequest,
            function (ServerRequestInterface $request) {
                throw new \RuntimeException('Buffered ' . $request->getBody()->getSize(), 42);
            }
        );

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertTrue($stream->isWritable());
        $stream->end('Foo');
        $this->assertFalse($stream->isWritable());

        assert($exception instanceof \RuntimeException);
        $this->assertInstanceOf('RuntimeException', $exception);
        $this->assertEquals('Buffered 3', $exception->getMessage());
        $this->assertEquals(42, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    /**
     * @requires PHP 7
     */
    public function testBufferingRejectsWhenNextHandlerThrowsErrorWhenStreamEnds()
    {
        $stream = new ThroughStream();

        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            new HttpBodyStream($stream, null)
        );

        $buffer = new RequestBodyBufferMiddleware(100);
        $promise = $buffer(
            $serverRequest,
            function (ServerRequestInterface $request) {
                throw new \Error('Buffered ' . $request->getBody()->getSize(), 42);
            }
        );

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertTrue($stream->isWritable());
        $stream->end('Foo');
        $this->assertFalse($stream->isWritable());

        assert($exception instanceof \Error);
        $this->assertInstanceOf('Error', $exception);
        $this->assertEquals('Buffered 3', $exception->getMessage());
        $this->assertEquals(42, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testBufferingRejectsWhenStreamEmitsError()
    {
        $stream = new ThroughStream(function ($data) {
            throw new \UnexpectedValueException('Unexpected ' . $data, 42);
        });

        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            new HttpBodyStream($stream, null)
        );

        $buffer = new RequestBodyBufferMiddleware(1);
        $promise = $buffer(
            $serverRequest,
            $this->expectCallableNever()
        );

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertTrue($stream->isWritable());
        $stream->write('Foo');
        $this->assertFalse($stream->isWritable());

        assert($exception instanceof \RuntimeException);
        $this->assertInstanceOf('RuntimeException', $exception);
        $this->assertEquals('Error while buffering request body: Unexpected Foo', $exception->getMessage());
        $this->assertEquals(42, $exception->getCode());
        $this->assertInstanceOf('UnexpectedValueException', $exception->getPrevious());
    }

    public function testFullBodyStreamedBeforeCallingNextMiddleware()
    {
        $promiseResolved = false;
        $middleware = new RequestBodyBufferMiddleware(3);
        $stream = new ThroughStream();
        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            new HttpBodyStream($stream, null)
        );

        $middleware($serverRequest, function () {
            return new Response();
        })->then(function () use (&$promiseResolved) {
            $promiseResolved = true;
        });

        $stream->write('aaa');
        $this->assertFalse($promiseResolved);
        $stream->write('aaa');
        $this->assertFalse($promiseResolved);
        $stream->end('aaa');
        $this->assertTrue($promiseResolved);
    }

    public function testCancelBufferingClosesStreamAndRejectsPromise()
    {
        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $serverRequest = new ServerRequest(
            'GET',
            'https://example.com/',
            array(),
            new HttpBodyStream($stream, 2)
        );

        $buffer = new RequestBodyBufferMiddleware(2);

        $promise = $buffer($serverRequest, $this->expectCallableNever());
        $promise->cancel();

        $this->assertFalse($stream->isReadable());

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        assert($exception instanceof \RuntimeException);
        $this->assertInstanceOf('RuntimeException', $exception);
        $this->assertEquals('Cancelled buffering request body', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }
}
