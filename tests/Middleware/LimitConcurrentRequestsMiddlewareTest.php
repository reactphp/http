<?php

namespace React\Tests\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\HttpBodyStream;
use React\Http\Io\ServerRequest;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;
use React\Promise\PromiseInterface;
use React\Http\Response;

final class LimitConcurrentRequestsMiddlewareTest extends TestCase
{
    public function testLimitOneRequestConcurrently()
    {
        /**
         * The first request
         */
        $requestA = new ServerRequest('GET', 'https://example.com/');
        $deferredA = new Deferred();
        $calledA = false;
        $nextA = function () use (&$calledA, $deferredA) {
            $calledA = true;
            return $deferredA->promise();
        };

        /**
         * The second request
         */
        $requestB = new ServerRequest('GET', 'https://www.example.com/');
        $deferredB = new Deferred();
        $calledB = false;
        $nextB = function () use (&$calledB, $deferredB) {
            $calledB = true;
            return $deferredB->promise();
        };

        /**
         * The third request
         */
        $requestC = new ServerRequest('GET', 'https://www.example.com/');
        $calledC = false;
        $nextC = function () use (&$calledC) {
            $calledC = true;
        };

        /**
         * The handler
         *
         */
        $limitHandlers = new LimitConcurrentRequestsMiddleware(1);

        $this->assertFalse($calledA);
        $this->assertFalse($calledB);
        $this->assertFalse($calledC);

        $limitHandlers($requestA, $nextA);

        $this->assertTrue($calledA);
        $this->assertFalse($calledB);
        $this->assertFalse($calledC);

        $limitHandlers($requestB, $nextB);

        $this->assertTrue($calledA);
        $this->assertFalse($calledB);
        $this->assertFalse($calledC);

        $limitHandlers($requestC, $nextC);

        $this->assertTrue($calledA);
        $this->assertFalse($calledB);
        $this->assertFalse($calledC);

        /**
         * Ensure resolve frees up a slot
         */
        $deferredA->resolve();

        $this->assertTrue($calledA);
        $this->assertTrue($calledB);
        $this->assertFalse($calledC);

        /**
         * Ensure reject also frees up a slot
         */
        $deferredB->reject();

        $this->assertTrue($calledA);
        $this->assertTrue($calledB);
        $this->assertTrue($calledC);
    }

    public function testReturnsResponseDirectlyFromMiddlewareWhenBelowLimit()
    {
        $middleware = new LimitConcurrentRequestsMiddleware(1);

        $response = new Response();
        $ret = $middleware(new ServerRequest('GET', 'https://example.com/'), function () use ($response) {
            return $response;
        });

        $this->assertSame($response, $ret);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage demo
     */
    public function testThrowsExceptionDirectlyFromMiddlewareWhenBelowLimit()
    {
        $middleware = new LimitConcurrentRequestsMiddleware(1);

        $middleware(new ServerRequest('GET', 'https://example.com/'), function () {
            throw new \RuntimeException('demo');
        });
    }

    /**
     * @requires PHP 7
     * @expectedException Error
     * @expectedExceptionMessage demo
     */
    public function testThrowsErrorDirectlyFromMiddlewareWhenBelowLimit()
    {
        $middleware = new LimitConcurrentRequestsMiddleware(1);

        $middleware(new ServerRequest('GET', 'https://example.com/'), function () {
            throw new \Error('demo');
        });
    }

    public function testReturnsPendingPromiseChainedFromMiddlewareWhenBelowLimit()
    {
        $middleware = new LimitConcurrentRequestsMiddleware(1);

        $deferred = new Deferred();
        $ret = $middleware(new ServerRequest('GET', 'https://example.com/'), function () use ($deferred) {
            return $deferred->promise();
        });

        $this->assertTrue($ret instanceof PromiseInterface);
    }

    public function testReturnsPendingPromiseFromMiddlewareWhenAboveLimit()
    {
        $middleware = new LimitConcurrentRequestsMiddleware(1);

        $middleware(new ServerRequest('GET', 'https://example.com/'), function () {
            return new Promise(function () { });
        });

        $ret = $middleware(new ServerRequest('GET', 'https://example.com/'), function () {
            return new Response();
        });

        $this->assertTrue($ret instanceof PromiseInterface);
    }

    public function testStreamDoesNotPauseOrResumeWhenBelowLimit()
    {
        $body = $this->getMockBuilder('React\Http\Io\HttpBodyStream')->disableOriginalConstructor()->getMock();
        $body->expects($this->never())->method('pause');
        $body->expects($this->never())->method('resume');
        $limitHandlers = new LimitConcurrentRequestsMiddleware(1);
        $limitHandlers(new ServerRequest('GET', 'https://example.com/', array(), $body), function () {});
    }

    public function testStreamDoesPauseWhenAboveLimit()
    {
        $body = $this->getMockBuilder('React\Http\Io\HttpBodyStream')->disableOriginalConstructor()->getMock();
        $body->expects($this->once())->method('pause');
        $body->expects($this->never())->method('resume');
        $limitHandlers = new LimitConcurrentRequestsMiddleware(1);

        $limitHandlers(new ServerRequest('GET', 'https://example.com'), function () {
            return new Promise(function () { });
        });

        $limitHandlers(new ServerRequest('GET', 'https://example.com/', array(), $body), function () {});
    }

    public function testStreamDoesPauseAndThenResumeWhenDequeued()
    {
        $body = $this->getMockBuilder('React\Http\Io\HttpBodyStream')->disableOriginalConstructor()->getMock();
        $body->expects($this->once())->method('pause');
        $body->expects($this->once())->method('resume');
        $limitHandlers = new LimitConcurrentRequestsMiddleware(1);

        $deferred = new Deferred();
        $limitHandlers(new ServerRequest('GET', 'https://example.com'), function () use ($deferred) {
            return $deferred->promise();
        });

        $limitHandlers(new ServerRequest('GET', 'https://example.com/', array(), $body), function () {});

        $deferred->reject();
    }

    public function testReceivesBufferedRequestSameInstance()
    {
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            'hello'
        );

        $req = null;
        $middleware = new LimitConcurrentRequestsMiddleware(1);
        $middleware($request, function (ServerRequestInterface $request) use (&$req) {
            $req = $request;
        });

        $this->assertSame($request, $req);
    }

    public function testReceivesStreamingBodyRequestSameInstanceWhenBelowLimit()
    {
        $stream = new ThroughStream();
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            new HttpBodyStream($stream, 5)
        );

        $req = null;
        $middleware = new LimitConcurrentRequestsMiddleware(1);
        $middleware($request, function (ServerRequestInterface $request) use (&$req) {
            $req = $request;
        });

        $this->assertSame($request, $req);

        $body = $req->getBody();
        $body->on('data', $this->expectCallableOnce('hello'));
        $stream->write('hello');
    }

    public function testReceivesRequestsSequentially()
    {
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            'hello'
        );

        $middleware = new LimitConcurrentRequestsMiddleware(1);
        $middleware($request, $this->expectCallableOnceWith($request));
        $middleware($request, $this->expectCallableOnceWith($request));
        $middleware($request, $this->expectCallableOnceWith($request));
    }

    public function testDoesNotReceiveNextRequestIfHandlerIsPending()
    {
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            'hello'
        );

        $middleware = new LimitConcurrentRequestsMiddleware(1);
        $middleware($request, function () {
            return new Promise(function () {
                // NO-OP: pending promise
            });
        });

        $middleware($request, $this->expectCallableNever());
    }

    public function testReceivesNextRequestAfterPreviousHandlerIsSettled()
    {
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            'hello'
        );

        $deferred = new Deferred();
        $middleware = new LimitConcurrentRequestsMiddleware(1);
        $middleware($request, function () use ($deferred) {
            return $deferred->promise();
        });

        $deferred->reject(new \RuntimeException());

        $middleware($request, $this->expectCallableOnceWith($request));
    }

    public function testReceivesNextRequestWhichThrowsAfterPreviousHandlerIsSettled()
    {
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            'hello'
        );

        $deferred = new Deferred();
        $middleware = new LimitConcurrentRequestsMiddleware(1);
        $middleware($request, function () use ($deferred) {
            return $deferred->promise();
        });

        $second = $middleware($request, function () {
            throw new \RuntimeException();
        });

        $this->assertTrue($second instanceof PromiseInterface);
        $second->then(null, $this->expectCallableOnce());

        $deferred->reject(new \RuntimeException());
    }

    public function testPendingRequestCanBeCancelledAndForwardsCancellationToInnerPromise()
    {
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            'hello'
        );

        $once = $this->expectCallableOnce();
        $deferred = new Deferred(function () use ($once) {
            $once();
            throw new \RuntimeException('Cancelled');
        });
        $middleware = new LimitConcurrentRequestsMiddleware(1);
        $promise = $middleware($request, function () use ($deferred) {
            return $deferred->promise();
        });

        $this->assertTrue($promise instanceof PromiseInterface);
        $promise->cancel();
    }

    public function testQueuedRequestCanBeCancelledBeforeItStartsProcessing()
    {
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            'hello'
        );

        $deferred = new Deferred();
        $middleware = new LimitConcurrentRequestsMiddleware(1);
        $middleware($request, function () use ($deferred) {
            return $deferred->promise();
        });

        $promise = $middleware($request, $this->expectCallableNever());

        $this->assertTrue($promise instanceof PromiseInterface);
        $promise->cancel();
        $promise->then(null, $this->expectCallableOnce());
    }

    public function testReceivesNextRequestAfterPreviousHandlerIsCancelled()
    {
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            'hello'
        );

        $deferred = new Deferred(function () {
            throw new \RuntimeException('Cancelled');
        });
        $middleware = new LimitConcurrentRequestsMiddleware(1);
        $promise = $middleware($request, function () use ($deferred) {
            return $deferred->promise();
        });

        $this->assertTrue($promise instanceof PromiseInterface);
        $promise->cancel();
        $promise->then(null, $this->expectCallableOnce());

        $middleware($request, $this->expectCallableOnceWith($request));
    }

    public function testRejectsWhenQueuedPromiseIsCancelled()
    {
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            'hello'
        );

        $deferred = new Deferred();
        $middleware = new LimitConcurrentRequestsMiddleware(1);
        $first = $middleware($request, function () use ($deferred) {
            return $deferred->promise();
        });

        $second = $middleware($request, $this->expectCallableNever());

        $this->assertTrue($second instanceof PromiseInterface);
        $second->cancel();
        $second->then(null, $this->expectCallableOnce());
    }

    public function testDoesNotInvokeNextHandlersWhenQueuedPromiseIsCancelled()
    {
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            'hello'
            );

        $deferred = new Deferred();
        $middleware = new LimitConcurrentRequestsMiddleware(1);
        $first = $middleware($request, function () use ($deferred) {
            return $deferred->promise();
        });

        $second = $middleware($request, $this->expectCallableNever());
        /* $third = */ $middleware($request, $this->expectCallableNever());

        $this->assertTrue($second instanceof PromiseInterface);
        $second->cancel();
    }

    public function testReceivesStreamingBodyChangesInstanceWithCustomBodyButSameDataWhenDequeued()
    {
        $stream = new ThroughStream();
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            new HttpBodyStream($stream, 5)
        );

        $middleware = new LimitConcurrentRequestsMiddleware(1);

        $deferred = new Deferred();
        $middleware(new ServerRequest('GET', 'https://example.com/'), function () use ($deferred) {
            return $deferred->promise();
        });

        $req = null;
        $middleware($request, function (ServerRequestInterface $request) use (&$req) {
            $req = $request;
        });

        $deferred->reject();

        $this->assertNotSame($request, $req);
        $this->assertInstanceOf('Psr\Http\Message\ServerRequestInterface', $req);

        $body = $req->getBody();
        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);
        /* @var $body \React\Stream\ReadableStreamInterface */

        $this->assertEquals(5, $body->getSize());

        $body->on('data', $this->expectCallableOnce('hello'));
        $stream->write('hello');
    }

    public function testReceivesNextStreamingBodyWithBufferedDataAfterPreviousHandlerIsSettled()
    {
        $deferred = new Deferred();
        $middleware = new LimitConcurrentRequestsMiddleware(1);
        $middleware(new ServerRequest('GET', 'http://example.com/'), function () use ($deferred) {
            return $deferred->promise();
        });

        $stream = new ThroughStream();
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            new HttpBodyStream($stream, 10)
        );

        $once = $this->expectCallableOnceWith('helloworld');
        $middleware($request, function (ServerRequestInterface $request) use ($once) {
            $request->getBody()->on('data', $once);
        });

        $stream->write('hello');
        $stream->write('world');

        $deferred->reject(new \RuntimeException());
    }

    public function testReceivesNextStreamingBodyAndDoesNotEmitDataIfExplicitlyClosed()
    {
        $deferred = new Deferred();
        $middleware = new LimitConcurrentRequestsMiddleware(1);
        $middleware(new ServerRequest('GET', 'http://example.com/'), function () use ($deferred) {
            return $deferred->promise();
        });

        $stream = new ThroughStream();
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            new HttpBodyStream($stream, 10)
        );

        $never = $this->expectCallableNever();
        $middleware($request, function (ServerRequestInterface $request) use ($never) {
            $request->getBody()->close();
            $request->getBody()->on('data', $never);
        });

        $stream->write('hello');
        $stream->write('world');

        $deferred->reject(new \RuntimeException());
    }

    public function testReceivesNextStreamingBodyAndDoesNotEmitDataIfExplicitlyPaused()
    {
        $deferred = new Deferred();
        $middleware = new LimitConcurrentRequestsMiddleware(1);
        $middleware(new ServerRequest('GET', 'http://example.com/'), function () use ($deferred) {
            return $deferred->promise();
        });

        $stream = new ThroughStream();
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            new HttpBodyStream($stream, 10)
        );

        $never = $this->expectCallableNever();
        $middleware($request, function (ServerRequestInterface $request) use ($never) {
            $request->getBody()->pause();
            $request->getBody()->on('data', $never);
        });

        $stream->write('hello');
        $stream->write('world');

        $deferred->reject(new \RuntimeException());
    }

    public function testReceivesNextStreamingBodyAndDoesEmitDataImmediatelyIfExplicitlyResumed()
    {
        $deferred = new Deferred();
        $middleware = new LimitConcurrentRequestsMiddleware(1);
        $middleware(new ServerRequest('GET', 'http://example.com/'), function () use ($deferred) {
            return $deferred->promise();
        });

        $stream = new ThroughStream();
        $request = new ServerRequest(
            'POST',
            'http://example.com/',
            array(),
            new HttpBodyStream($stream, 10)
        );

        $once = $this->expectCallableOnceWith('helloworld');
        $never = $this->expectCallableNever();
        $middleware($request, function (ServerRequestInterface $request) use ($once, $never) {
            $request->getBody()->on('data', $once);
            $request->getBody()->resume();
            $request->getBody()->on('data', $never);
        });

        $stream->write('hello');
        $stream->write('world');

        $deferred->reject(new \RuntimeException());
    }
}
