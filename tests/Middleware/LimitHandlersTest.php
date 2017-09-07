<?php

namespace React\Tests\Http\Middleware;

use React\Http\Middleware\LimitHandlers;
use React\Promise\Deferred;
use React\Tests\Http\TestCase;
use RingCentral\Psr7\Request;

final class LimitHandlersTest extends TestCase
{
    public function testNonStreamingBody()
    {
        /**
         * The first request
         */
        $requestA = new Request('GET', 'https://example.com/');
        $deferredA = new Deferred();
        $calledA = false;
        $nextA = function () use (&$calledA) {
            $calledA = true;
        };

        /**
         * The second request
         */
        $requestB = new Request('GET', 'https://www.example.com/');

        $limitHandlers = new LimitHandlers(1);

    }
}
