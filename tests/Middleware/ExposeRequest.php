<?php

namespace React\Tests\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise;

final class ExposeRequest
{
    /**
     * @var ServerRequestInterface
     */
    private $request;

    public function __invoke(ServerRequestInterface $request, callable $stack)
    {
        $this->request = $request;
        return Promise\resolve($stack($request));
    }

    /**
     * @return ServerRequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }
}
