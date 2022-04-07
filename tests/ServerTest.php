<?php

namespace React\Tests\Http;

use React\Http\Server;

class ServerTest extends TestCase
{
    public function testDeprecatedServerIsInstanceOfNewHttpServer()
    {
        $http = new Server(function () { });

        $this->assertInstanceOf('React\Http\HttpServer', $http);
    }
}
