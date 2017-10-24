<?php

namespace React\Http;

use React\Socket\ConnectionInterface;

interface RequestHeaderParserFactoryInterface
{

    /**
     * @param ConnectionInterface $conn
     * @return RequestHeaderParserInterface
     */
    public function create(ConnectionInterface $conn);
}
