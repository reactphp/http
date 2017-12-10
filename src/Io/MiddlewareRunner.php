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
     * @internal
     */
    public $middleware = array();

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

        $position = 0;

        $that = $this;
        $func = function (ServerRequestInterface $request) use (&$func, &$position, &$that) {
            $middleware = $that->middleware[$position];
            $response = null;
            $promise = new Promise\Promise(function ($resolve) use ($middleware, $request, $func, &$response, &$position) {
                $position++;

                $response = $middleware(
                    $request,
                    $func
                );

                $resolve($response);
            }, function () use (&$response) {
                if ($response instanceof Promise\CancellablePromiseInterface) {
                    $response->cancel();
                }
            });

            return $promise->then(null, function ($error) use (&$position) {
                $position--;

                return Promise\reject($error);
            });
        };

        return $func($request);
    }
}
