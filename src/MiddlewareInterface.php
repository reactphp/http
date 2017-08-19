<?php

namespace React\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

interface MiddlewareInterface
{
    /**
     * Process an incoming server request and return a response, optionally delegating
     * to the next middleware component to create the response.
     *
     * @param ServerRequestInterface $request
     * @param MiddlewareStackInterface $stack
     *
     * @return ResponseInterface|PromiseInterface<ResponseInterface>
     */
    public function process(ServerRequestInterface $request, MiddlewareStackInterface $stack);
}
