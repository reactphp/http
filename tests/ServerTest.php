<?php

namespace React\Tests\Http;

use React\Http\Server;
use React\Http\Response;
use React\Http\Request;
use React\Socket\Server as Socket;

class ServerTest extends TestCase
{
    private $connection;
    private $loop;

    public function setUp()
    {
        $this->loop = \React\EventLoop\Factory::create();

        $this->connection = $this->getMockBuilder('React\Socket\Connection')
            ->disableOriginalConstructor()
            ->setMethods(
                array(
                    'write',
                    'end',
                    'close',
                    'pause',
                    'resume',
                    'isReadable',
                    'isWritable',
                    'getRemoteAddress',
                    'pipe'
                )
            )
            ->getMock();
    }

    public function testRequestEventIsEmitted()
    {
        $socket = new Socket($this->loop);

        $server = new Server($socket);
        $server->on('request', $this->expectCallableOnce());

        $socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestEvent()
    {
        $socket = new Socket($this->loop);

        $i = 0;
        $requestAssertion = null;
        $responseAssertion = null;

        $server = new Server($socket);
        $server->on('request', function ($request, $response) use (&$i, &$requestAssertion, &$responseAssertion) {
            $i++;
            $requestAssertion = $request;
            $responseAssertion = $response;
        });

        $this->connection
            ->expects($this->once())
            ->method('getRemoteAddress')
            ->willReturn('127.0.0.1');

        $socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));

        $this->assertSame(1, $i);
        $this->assertInstanceOf('React\Http\Request', $requestAssertion);
        $this->assertSame('/', $requestAssertion->getPath());
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('127.0.0.1', $requestAssertion->remoteAddress);

        $this->assertInstanceOf('React\Http\Response', $responseAssertion);
    }

    public function testResponseContainsPoweredByHeader()
    {
        $socket = new Socket($this->loop);

        $server = new Server($socket);
        $server->on('request', function (Request $request, Response $response) {
            $response->writeHead();
            $response->end();
        });

        $this->connection
            ->expects($this->exactly(2))
            ->method('write')
            ->withConsecutive(
                array($this->equalTo("HTTP/1.1 200 OK\r\nX-Powered-By: React/alpha\r\nTransfer-Encoding: chunked\r\n\r\n")),
                array($this->equalTo("0\r\n\r\n"))
            );

        $socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testParserErrorEmitted()
    {
        $socket = new Socket($this->loop);

        $error = null;
        $server = new Server($socket);
        $server->on('headers', $this->expectCallableNever());
        $server->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: example.com\r\nConnection: close\r\nX-DATA: ";
        $data .= str_repeat('A', 4097 - strlen($data)) . "\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf('OverflowException', $error);
        $this->connection->expects($this->never())->method('write');
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
