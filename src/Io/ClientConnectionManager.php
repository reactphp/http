<?php

namespace React\Http\Io;

use Psr\Http\Message\UriInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;

/**
 * [Internal] Manages outgoing HTTP connections for the HTTP client
 *
 * @internal
 * @final
 */
class ClientConnectionManager
{
    /** @var ConnectorInterface */
    private $connector;

    public function __construct(ConnectorInterface $connector)
    {
        $this->connector = $connector;
    }

    /**
     * @return PromiseInterface<ConnectionInterface>
     */
    public function connect(UriInterface $uri)
    {
        $scheme = $uri->getScheme();
        if ($scheme !== 'https' && $scheme !== 'http') {
            return \React\Promise\reject(new \InvalidArgumentException(
                'Invalid request URL given'
            ));
        }

        $port = $uri->getPort();
        if ($port === null) {
            $port = $scheme === 'https' ? 443 : 80;
        }

        return $this->connector->connect(($scheme === 'https' ? 'tls://' : '') . $uri->getHost() . ':' . $port);
    }

    /**
     * @return void
     */
    public function handBack(ConnectionInterface $connection)
    {
        $connection->close();
    }
}
