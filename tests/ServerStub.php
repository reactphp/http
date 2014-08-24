<?php

namespace React\Tests\Http;

use Evenement\EventEmitter;
use React\Socket\ServerInterface;

class ServerStub extends EventEmitter implements ServerInterface
{
    public function listen($address)
    {
    }

    public function getAddress()
    {
        return '127.0.0.1:80';
    }

    public function shutdown()
    {
    }
}
