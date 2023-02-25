<?php

namespace React\Http\Client;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\Stream\DuplexStreamInterface;

class UpgradedResponse
{
    /**
     * @var DuplexStreamInterface
     */
    private $connection;

    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(DuplexStreamInterface $connection, ResponseInterface $response, RequestInterface $request)
    {
        $this->connection = $connection;
        $this->response = $response;
        $this->request = $request;
    }

    /**
     * @return DuplexStreamInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }
}
