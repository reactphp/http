<?php

namespace React\Http\Io;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise;
use React\Promise\PromiseInterface;

/**
 * [Internal] Middleware runner to expose an array of middleware request handlers as a single request handler callable
 *
 * @internal
 */
final class MiddlewareRunner
{
    /**
     * @var callable[]
     */
    private $middleware = array();

    /**
     * @param callable[] $middleware
     */
    public function __construct(array $middleware)
    {
        $this->middleware = array_values($middleware);
    }

    /**
     * @param ServerRequestInterface $request
     * @return PromiseInterface<ResponseInterface>
     */
    public function __invoke(ServerRequestInterface $request)
    {
        if (count($this->middleware) === 0) {
            return Promise\reject(new \RuntimeException('No middleware to run'));
        }

        return $this->call($request, 0);
    }

    /** @internal */
    public function call(ServerRequestInterface $request, $position)
    {
        $that = $this;
        $next = function (ServerRequestInterface $request) use ($that, $position) {
            return $that->call($request, $position + 1);
        };

        $handler = $this->middleware[$position];
        try {
            return Promise\resolve($handler($request, $next));
        } catch (\Exception $error) {
            // request handler callback throws an Exception
            return Promise\reject($error);
        } catch (\Throwable $error) { // @codeCoverageIgnoreStart
            // request handler callback throws a PHP7+ Error
            return Promise\reject($error); // @codeCoverageIgnoreEnd
        }
    }
}
