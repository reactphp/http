<?php

namespace React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\MiddlewareInterface;
use React\Http\MiddlewareStackInterface;
use React\Promise\Deferred;

final class LimitHandlers
{
    private $limit = 10;
    private $pending = 0;
    private $queued;

    public function __construct($limit = 10)
    {
        $this->limit = $limit;
        $this->queued = new \SplQueue();
    }

    public function __invoke(ServerRequestInterface $request, callable $stack)
    {
        $deferred = new Deferred();
        $this->queued->enqueue($deferred);

        $this->processQueue();

        return $deferred->promise()->then(function () use ($request, $stack) {
            $this->pending++;
            return $stack($request);
        })->then(function ($response) {
            $this->pending--;
            $this->processQueue();
            return $response;
        });
    }

    private function processQueue()
    {
        if ($this->pending >= $this->limit) {
            return;
        }

        if ($this->queued->count() === 0) {
            return;
        }

        $this->queued->dequeue()->resolve();
    }
}
