<?php

namespace React\Tests\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\MiddlewareInterface;
use React\Http\MiddlewareStackInterface;
use React\Promise;

final class ExposeRequest implements MiddlewareInterface
{
    /**
     * @var ServerRequestInterface
     */
    private $request;

    public function process(ServerRequestInterface $request, MiddlewareStackInterface $stack)
    {
        $this->request = $request;
        return Promise\resolve($stack->process($request));
    }

    /**
     * @return ServerRequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }
}
