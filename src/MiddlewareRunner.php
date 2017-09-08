<?php

namespace React\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise;
use React\Promise\PromiseInterface;

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
        $this->middleware = $middleware;
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

        $middlewareCollection = $this->middleware;
        $middleware = array_shift($middlewareCollection);

        $cancel = null;
        return new Promise\Promise(function ($resolve, $reject) use ($middleware, $request, $middlewareCollection, &$cancel) {
            $cancel = $middleware(
                $request,
                new MiddlewareRunner(
                    $middlewareCollection
                )
            );
            $resolve($cancel);
        }, function () use (&$cancel) {
            if ($cancel instanceof Promise\CancellablePromiseInterface) {
                $cancel->cancel();
            }
        });
    }
}
