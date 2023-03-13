<?php

namespace React\Http\Browser;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\Promise\PromiseInterface;

interface MiddlewareInterface
{
    /**
     * @param RequestInterface $request
     * @param callable $next
     * @return PromiseInterface<ResponseInterface>
     */
    public function __invoke(RequestInterface $request, callable $next);
}
