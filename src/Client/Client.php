<?php

namespace React\Http\Client;

use Psr\Http\Message\RequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Io\ClientRequestStream;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;

/**
 * @internal
 */
class Client
{
    private $connector;

    public function __construct(LoopInterface $loop, ConnectorInterface $connector = null)
    {
        if ($connector === null) {
            $connector = new Connector(array(), $loop);
        }

        $this->connector = $connector;
    }

    /** @return ClientRequestStream */
    public function request(RequestInterface $request)
    {
        return new ClientRequestStream($this->connector, $request);
    }
}
