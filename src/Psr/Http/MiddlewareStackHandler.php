<?php

namespace React\Http\Psr\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareStackHandler implements RequestHandlerInterface
{
    /**
     * @var RequestHandlerInterface
     */
    private $handler;

    /**
     * @var MiddlewareInterface[]
     */
    private $middlewareStack;

    /**
     * @var int
     */
    private $currentMiddleware;

    /**
     * @param RequestHandlerInterface $handler
     * @param MiddlewareInterface[] $middlewareStack
     */
    public function __construct(RequestHandlerInterface $handler, array $middlewareStack)
    {
        $this->handler = $handler;
        $this->middlewareStack = array();

        foreach ($middlewareStack as $middleware) {
            if (!$middleware instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException(
                    'All middlewares in the stack must implement the Psr\\Http\\Server\\MiddlewareInterface.'
                );
            }

            $this->middlewareStack[] = $middleware;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = current($this->middlewareStack);

        if ($middleware instanceof MiddlewareInterface) {
            next($this->middlewareStack);

            return $middleware->process($request, $this);
        } else {
            return $this->handler->handle($request);
        }
    }
}
