<?php

namespace React\Http;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

interface MiddlewareStackInterface
{
    /**
     * @param ServerRequestInterface $request
     * @return PromiseInterface<ResponseInterface>
     */
    public function process(ServerRequestInterface $request);
}
