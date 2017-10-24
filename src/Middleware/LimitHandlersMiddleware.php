<?php

namespace React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Deferred;
use React\Stream\ReadableStreamInterface;
use SplQueue;

final class LimitHandlersMiddleware
{
    const DEFAULT_LIMIT = 10;

    private $limit = self::DEFAULT_LIMIT;
    private $pending = 0;
    private $queued;

    /**
     * @param int $limit Maximum amount of concurrent requests handled.
     *
     * For example when $limit is set to 10, 10 requests will flow to $next
     * while more incoming requests have to wait until one is done.
     */
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

        $that = $this;
        $pending = &$this->pending;
        return $deferred->promise()->then(function () use ($request, $next, &$pending) {
            $pending++;
            $body = $request->getBody();
            if ($body instanceof ReadableStreamInterface) {
                $body->resume();
            }
            return $next($request);
        })->then(function ($response) use ($that, &$pending) {
            $pending--;
            $that->processQueue();
            return $response;
        }, function ($error) use ($that, &$pending) {
            $pending--;
            $that->processQueue();
            return $error;
        });
    }

    /**
     * @internal
     */
    public function processQueue()
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
