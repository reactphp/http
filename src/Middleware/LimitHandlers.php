<?php

namespace React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Deferred;
use React\Stream\ReadableStreamInterface;
use SplQueue;

final class LimitHandlers
{
    const DEFAULT_LIMIT = 10;

    private $limit = self::DEFAULT_LIMIT;
    private $pending = 0;
    private $queued;

    public function __construct($limit = self::DEFAULT_LIMIT)
    {
        $this->limit = $limit;
        $this->queued = new SplQueue();
    }

    public function __invoke(ServerRequestInterface $request, $next)
    {
        $body = $request->getBody();
        if ($body instanceof ReadableStreamInterface) {
            $body->pause();
        }
        $deferred = new Deferred();
        $this->queued->enqueue($deferred);

        $this->processQueue();

        return $deferred->promise()->then(function () use ($request, $next) {
            $this->pending++;
            $body = $request->getBody();
            if ($body instanceof ReadableStreamInterface) {
                $body->resume();
            }
            return $next($request);
        })->then(function ($response) {
            $this->pending--;
            $this->processQueue();
            return $response;
        }, function ($error) {
            $this->pending--;
            $this->processQueue();
            return $error;
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
