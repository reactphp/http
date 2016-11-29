<?php

namespace React\Tests\Http;

use React\Http\Request;
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

    public function testParserErrorEmitted()
    {
        $io = new ServerStub();

        $error = null;
        $server = new Server($io);
        $server->on('headers', $this->expectCallableNever());
        $server->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $conn = new ConnectionStub();
        $io->emit('connection', [$conn]);

        $data = $this->createGetRequest();
        $data = str_pad($data, 4096 * 4);
        $conn->emit('data', [$data]);

        $this->assertInstanceOf('OverflowException', $error);
        $this->assertEquals('', $conn->getData());
    }

    /**
     * @url https://github.com/reactphp/http/issues/84
     */
    public function testPauseResume()
    {
        $io = new ServerStub();

        $server = new Server($io);
        $called = false;
        $server->on('request', function (Request $request) use (&$called) {
            $called = true;

            $request->emit('pause');
            $request->emit('resume');
        });

        /** @var ConnectionStub|\PHPUnit_Framework_MockObject_MockObject $conn */
        $conn = $this->getMock(ConnectionStub::class, ['pause', 'resume'], [], '', false, false);
        $conn->expects($this->once())
            ->method('pause')
        ;
        $conn->expects($this->once())
            ->method('resume')
        ;
        $io->emit('connection', [$conn]);

        $data = $this->createGetRequest();
        $conn->emit('data', [$data]);

        $this->assertTrue($called);
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
