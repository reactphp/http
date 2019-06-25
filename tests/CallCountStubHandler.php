<?php

namespace React\Tests\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CallCountStubHandler implements RequestHandlerInterface
{
    /** @var int */
    private $callCount;

    /** @var ResponseInterface|\Exception */
    private $response;

    public function __construct($response)
    {
        $this->callCount = 0;
        $this->response = $response;
    }

    /**
     * @return int
     */
    public function getCallCount()
    {
        return $this->callCount;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->callCount++;

        if ($this->response instanceof ResponseInterface) {
            return $this->response;
        } elseif ($this->response instanceof \Exception) {
            throw $this->response;
        }
    }
}
