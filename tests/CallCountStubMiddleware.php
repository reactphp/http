<?php

namespace React\Tests\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CallCountStubMiddleware implements MiddlewareInterface
{
    /** @var int */
    private $callCount;

    public function __construct()
    {
        $this->callCount = 0;
    }

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->callCount++;

        return $handler->handle($request);
    }

    /**
     * @return int
     */
    public function getCallCount()
    {
        return $this->callCount;
    }
}
