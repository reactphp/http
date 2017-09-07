<?php

namespace React\Tests\Http\Middleware;

use React\Http\Middleware\LimitHandlersMiddleware;
use React\Http\Io\ServerRequest;
use React\Promise\Deferred;
use React\Tests\Http\TestCase;

final class LimitHandlersMiddlewareTest extends TestCase
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
        $limitHandlers = new LimitHandlersMiddleware(1);

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

    public function testStreamPauseAndResume()
    {
        $body = $this->getMockBuilder('React\Http\Io\HttpBodyStream')->disableOriginalConstructor()->getMock();
        $body->expects($this->once())->method('pause');
        $body->expects($this->once())->method('resume');
        $limitHandlers = new LimitHandlersMiddleware(1);
        $limitHandlers(new ServerRequest('GET', 'https://example.com/', array(), $body), function () {});
    }
}
