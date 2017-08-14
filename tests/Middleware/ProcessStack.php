<?php

namespace React\Tests\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\MiddlewareInterface;
use React\Http\MiddlewareStackInterface;
use React\Promise;

final class ProcessStack implements MiddlewareInterface
{
    /**
     * @var int
     */
    private $callCount = 0;

    public function process(ServerRequestInterface $request, MiddlewareStackInterface $stack)
    {
        $this->callCount++;
        return Promise\resolve($stack->process($request));
    }

    /**
     * @return int
     */
    public function getCallCount()
    {
        return $this->callCount;
    }
}
