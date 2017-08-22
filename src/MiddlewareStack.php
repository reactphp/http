<?php

namespace React\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise;
use React\Promise\PromiseInterface;

final class MiddlewareStack implements MiddlewareStackInterface
{
    /**
     * @var ResponseInterface
     */
    private $defaultResponse;

    /**
     * @var MiddlewareInterface[]
     */
    private $middlewares = array();

    /**
     * @param ResponseInterface $response
     * @param MiddlewareInterface[] $middlewares
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
    public function process(ServerRequestInterface $request)
    {
        if (count($this->middlewares) === 0) {
            return Promise\resolve($this->defaultResponse);
        }

        $middlewares = $this->middlewares;
        $middleware = array_shift($middlewares);

        $cancel = null;
        return new Promise\Promise(function ($resolve, $reject) use ($middleware, $request, $middlewares, &$cancel) {
            $cancel = $middleware->process(
                $request,
                new MiddlewareStack(
                    $this->defaultResponse,
                    $middlewares
                )
            );
            $cancel->done($resolve, $reject);
        }, function () use (&$cancel) {
            if ($cancel instanceof Promise\CancellablePromiseInterface) {
                $cancel->cancel();
            }
        });
    }
}
