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
            $this->assertSame('127.0.0.1', $request->getRemoteAddress());

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

    private function createGetRequest()
    {
        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "\r\n";

        return $data;
    }

    public function testServerRespondsToExpectContinue()
    {
        $io = new ServerStub();
        $server = new Server($io);
        $conn = new ConnectionStub();
        $io->emit('connection', array($conn));

        $requestReceived = false;
        $postBody = '{"React":true}';
        $httpRequestText = $this->createPostRequestWithExpect($postBody);

        $conn->emit('data', array($httpRequestText));

        $server->on('request', function ($request, $_) use (&$requestReceived, $postBody) {
            $requestReceived = true;
            $this->assertEquals($postBody, $request->getBody());
        });

        // If server received Expect: 100-continue - the client won't send the body right away
        $this->assertEquals(false, $requestReceived);

        $this->assertEquals("HTTP/1.1 100 Continue\r\n\r\n", $conn->getData());

        $conn->emit('data', array($postBody));

        $this->assertEquals(true, $requestReceived);

    }

    private function createPostRequestWithExpect($postBody)
    {
        $data = "POST / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Content-Type: application/json\r\n";
        $data .= "Content-Length: " . strlen($postBody) . "\r\n";
        $data .= "Expect: 100-continue\r\n";
        $data .= "\r\n";

        return $data;
    }


}
