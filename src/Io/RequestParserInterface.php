<?php

namespace React\Http\Io;

use Evenement\EventEmitterInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Socket\ConnectionInterface;


interface RequestParserInterface extends EventEmitterInterface
{

    public function handle(ConnectionInterface $conn);

    /**
     * @param string $headers buffer string containing request headers only
     * @param ?string $remoteSocketUri
     * @param ?string $localSocketUri
     * @return ServerRequestInterface
     * @throws \InvalidArgumentException
     */
    public function parseRequest($headers, $remoteSocketUri, $localSocketUri);

}
