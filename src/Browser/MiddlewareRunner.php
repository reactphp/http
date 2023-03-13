<?php

namespace React\Http\Browser;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\Http\Io\Transaction;
use React\Promise\PromiseInterface;

/**
 * [Internal] Middleware runner to expose an array of middleware request handlers as a single request handler callable
 *
 * @internal
 */
final class MiddlewareRunner
{
    /** @var MiddlewareInterface[] */
    private $middleware;

    /** @var Transaction */
    private $transaction;

    /**
     * @param MiddlewareInterface[] $middleware
     */
    public function __construct(array $middleware, Transaction $transaction)
    {
        $this->middleware = \array_values($middleware);
        $this->transaction = $transaction;
    }

    /**
     * @param RequestInterface $request
     * @return ResponseInterface|PromiseInterface<ResponseInterface>
     * @throws \Exception
     */
    public function __invoke(RequestInterface $request)
    {
        return $this->call($request, 0);
    }

    /** @internal */
    public function call(RequestInterface $request, $position)
    {
        if (count($this->middleware) === 0) {
            return $this->transaction->send($request);
        }

        // final request handler will be invoked with transaction send callable
        if (!isset($this->middleware[$position + 1])) {
            $handler = $this->middleware[$position];
            $that = $this;
            return $handler($request, function () use ($that, $request) {
                return $that->transaction->send($request);
            });
        }

        $that = $this;
        $next = function (RequestInterface $request) use ($that, $position) {
            return $that->call($request, $position + 1);
        };

        // invoke middleware request handler with next handler
        $handler = $this->middleware[$position];
        return $handler($request, $next);
    }
}
