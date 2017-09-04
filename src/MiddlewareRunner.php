<?php

namespace React\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise;
use React\Promise\PromiseInterface;

class MiddlewareRunner
{
    /**
     * @var ResponseInterface
     */
    private $defaultResponse;

    /**
     * @var callable[]
     */
    private $middlewares = array();

    /**
     * @param ResponseInterface $response
     * @param callable[] $middlewares
     */
    public function __construct(ResponseInterface $response, array $middlewares)
    {
        $this->defaultResponse = $response;
        $this->middlewares = $middlewares;
    }

    /**
     * @param ServerRequestInterface $request
     * @return PromiseInterface<ResponseInterface>
     */
    public function __invoke(ServerRequestInterface $request)
    {
        if (count($this->middlewares) === 0) {
            return Promise\resolve($this->defaultResponse);
        }

        $middlewares = $this->middlewares;
        $middleware = array_shift($middlewares);

        $that = $this;
        $cancel = null;
        return new Promise\Promise(function ($resolve, $reject) use ($middleware, $request, $middlewares, &$cancel, $that) {
            $cancel = $middleware(
                $request,
                new MiddlewareRunner(
                    $that->defaultResponse,
                    $middlewares
                )
            );
            $cancel->then($resolve, $reject);
        }, function () use (&$cancel) {
            if ($cancel instanceof Promise\CancellablePromiseInterface) {
                $cancel->cancel();
            }
        });
    }
}
