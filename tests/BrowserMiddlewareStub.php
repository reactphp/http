<?php

namespace React\Tests\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser\MiddlewareInterface;
use React\Promise\PromiseInterface;

final class BrowserMiddlewareStub implements MiddlewareInterface
{
    /** @var ResponseInterface */
    private $response;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * @param RequestInterface $request
     * @param callable $next
     * @return PromiseInterface<ResponseInterface>|ResponseInterface
     */
    public function __invoke(RequestInterface $request, callable $next)
    {
        return $this->response;
    }
}
