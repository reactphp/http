<?php

namespace React\Tests\Http;

use React\Http\Server;

class ServerTest extends TestCase
{
    public function testRequestEventIsEmitted()
    {
        $server = new Server();
        $server->on('request', $this->expectCallableOnce());

        $conn = new ConnectionStub();
        $server->handleConnection($conn);

        $data = $this->createGetRequest();
        $conn->emit('data', array($data));

        $server->stop();
    }

    public function testRequestEvent()
    {
        $i = 0;

        $server = new Server();
        $server->on('request', function ($request, $response) use (&$i, $server) {
            $i++;

            $this->assertInstanceOf('React\Http\Request', $request);
            $this->assertSame('/', $request->getPath());
            $this->assertSame('GET', $request->getMethod());
            $this->assertSame('127.0.0.1', $request->remoteAddress);

            $this->assertInstanceOf('React\Http\Response', $response);

            $server->stop();
        });

        $conn = new ConnectionStub();
        $server->handleConnection($conn);

        $data = $this->createGetRequest();
        $conn->emit('data', array($data));

        $this->assertSame(1, $i);
    }

    public function testResponseContainsPoweredByHeader()
    {
        $server = new Server();
        $server->on('request', function ($request, $response) use($server) {
            $response->writeHead();
            $response->end();
        });

        $conn = new ConnectionStub();
        $server->handleConnection($conn);

        $data = $this->createGetRequest();
        $conn->emit('data', array($data));

        $this->assertContains("\r\nX-Powered-By: React/alpha\r\n", $conn->getData());

        $server->stop();
    }

    private function createGetRequest()
    {
        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "\r\n";

        return $data;
    }
}
