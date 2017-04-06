<?php

namespace React\Tests\Http;

use Evenement\EventEmitter;
use React\Socket\ServerInterface;

class SocketServerStub extends EventEmitter implements ServerInterface
{
     public function getAddress()
     {
         return '127.0.0.1:8080';
     }

     public function close()
     {
         // NO-OP
     }

     public function pause()
     {
         // NO-OP
     }

     public function resume()
     {
         // NO-OP
     }
}
