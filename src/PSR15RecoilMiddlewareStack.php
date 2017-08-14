<?php

namespace React\Http\Middleware;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Recoil\React\ReactKernel;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Http\MiddlewareInterface;
use React\Http\MiddlewareStackInterface;
use React\Promise;
use Psr\Http\Message\ServerRequestInterface;

final class PSR15RecoilMiddlewareStack implements MiddlewareStackInterface
{
    /**
     * @var ReactKernel
     */
    private $kernel;

    /**
     * @var ResponseInterface
     */
    private $defaultResponse;

    /**
     * @var DelegateInterface
     */
    private $middlewares;


    public static function create(LoopInterface $loop, ResponseInterface $response, DelegateInterface $middlewares)
    {
        return new self(ReactKernel::create($loop), $response, $middlewares);
    }

    private function __construct(ReactKernel $kernel, ResponseInterface $response, DelegateInterface $middlewares)
    {
        $this->kernel = $kernel;
        $this->defaultResponse = $response;
        $this->middlewares = $middlewares;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $stack)
    {
        if (count($this->middlewares) === 0) {
            return Promise\resolve($this->defaultResponse);
        }

        $middlewares = $this->middlewares;
        $middleware = array_shift($middlewares);

        return new Promise\Promise(function ($resolve, $reject) {
            $this->kernel->execute(function () use ($resolve, $reject) {
                $middleware->process(
                    $request,
                    new self(
                        $this->defaultResponse,
                        $middlewares
                    )
                );
            });
        });
    }
}
