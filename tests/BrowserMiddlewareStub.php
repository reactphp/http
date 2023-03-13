<?php

namespace React\Tests\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser\MiddlewareInterface;

final class BrowserMiddlewareStub implements MiddlewareInterface
{
    /** @var ResponseInterface */
    private $response;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function __invoke(RequestInterface $request, callable $next)
    {
        return $this->response;
    }
}
