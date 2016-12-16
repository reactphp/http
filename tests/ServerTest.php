<?php

namespace React\Tests\Http;

use React\Http\Server;

class ServerTest extends TestCase
{
    public function testRequestEventIsEmitted()
    {
        $io = new ServerStub();

        $server = new Server($io);
        $server->on('request', $this->expectCallableOnce());

        $conn = new ConnectionStub();
        $io->emit('connection', array($conn));

        $data = $this->createGetRequest();
        $conn->emit('data', array($data));
    }

    public function testRequestEvent()
    {
        $io = new ServerStub();

        $i = 0;

        $server = new Server($io);
        $server->on('request', function ($request, $response) use (&$i) {
            $i++;

            $this->assertInstanceOf('React\Http\Request', $request);
            $this->assertSame('/', $request->getPath());
            $this->assertSame('GET', $request->getMethod());
            $this->assertSame('127.0.0.1', $request->remoteAddress);

            $this->assertInstanceOf('React\Http\Response', $response);
        });

        $conn = new ConnectionStub();
        $io->emit('connection', array($conn));

        $data = $this->createGetRequest();
        $conn->emit('data', array($data));

        $this->assertSame(1, $i);
    }

    public function testResponseContainsPoweredByHeader()
    {
        $io = new ServerStub();

        $server = new Server($io);
        $server->on('request', function ($request, $response) {
            $response->writeHead();
            $response->end();
        });

        $conn = new ConnectionStub();
        $io->emit('connection', array($conn));

        $data = $this->createGetRequest();
        $conn->emit('data', array($data));

        $this->assertContains("\r\nX-Powered-By: React/alpha\r\n", $conn->getData());
    }

    public function testKeepaliveCloseAfterMax()
    {
        $io = new ServerStub();

        $server = new Server($io);
        $server->on('request', function ($request, $response) {
            $response->writeHead();
            $response->end();
        });

        $conn = new ConnectionStub();
        $io->emit('connection', array($conn));

        for ($i=0; $i<Server::KEEPALIVE_MAX_REQUEST; $i++) {
            $data = $this->createGetRequest(false);
            $conn->emit('data', array($data));
            $this->assertContains("\r\nConnection: keep-alive\r\n", $conn->getData());

            if ($i < Server::KEEPALIVE_MAX_REQUEST - 1) {
                $this->assertNotContains("\r\nConnection: close\r\n", $conn->getData());
            } else {
                $this->assertContains("\r\nConnection: close\r\n", $conn->getData());
            }
        }
    }

    public function testKeepaliveClose()
    {
        $io = new ServerStub();

        $server = new Server($io);
        $server->on('request', function ($request, $response) {
            $response->writeHead();
            $response->end();
        });

        $conn = new ConnectionStub();
        $io->emit('connection', array($conn));

        $data = $this->createGetRequest(true);
        $conn->emit('data', array($data));

        $this->assertContains("\r\nConnection: close\r\n", $conn->getData());
    }

    public function testKeepaliveKeep()
    {
        $io = new ServerStub();

        $server = new Server($io);
        $server->on('request', function ($request, $response) {
            $response->writeHead();
            $response->end();
        });

        $conn = new ConnectionStub();
        $io->emit('connection', array($conn));

        $data = $this->createGetRequest(false);
        $conn->emit('data', array($data));

        $this->assertContains("\r\nConnection: keep-alive\r\n", $conn->getData());
    }

    private function createGetRequest($close = true)
    {
        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        if ($close) {
            $data .= "Connection: close\r\n";
        } else {
            $data .= "Connection: keep-alive\r\n";
        }
        $data .= "\r\n";

        return $data;
    }
}
