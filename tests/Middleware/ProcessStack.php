<?php

namespace React\Tests\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise;

final class ProcessStack
{
    /**
     * @var int
     */
    private $callCount = 0;

    public function __invoke(ServerRequestInterface $request, callable $stack)
    {
        $this->callCount++;
        return Promise\resolve($stack($request));
    }

    /**
     * @return int
     */
    public function getCallCount()
    {
        return $this->callCount;
    }
}
