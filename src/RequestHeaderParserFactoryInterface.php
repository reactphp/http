<?php

namespace React\Http;

use React\Socket\ConnectionInterface;

interface RequestHeaderParserFactoryInterface
{

    /**
     * @param ConnectionInterface $conn
     * @return mixed
     */
    public function create(ConnectionInterface $conn);
}
