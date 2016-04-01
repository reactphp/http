<?php

namespace React\Tests\Http;

use React\Http\Server;
use React\Http\Response;
use React\Http\Request;

class ServerTest extends TestCase
{
    private $connection;
    private $socket;

    public function setUp()
    {
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

        $this->socket = $this->getMockBuilder('React\Socket\Server')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();
    }

    public function testRequestEventIsEmitted()
    {
        $server = new Server($this->socket);
        $server->on('request', $this->expectCallableOnce());

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestEvent()
    {
        $i = 0;
        $requestAssertion = null;
        $responseAssertion = null;

        $server = new Server($this->socket);
        $server->on('request', function ($request, $response) use (&$i, &$requestAssertion, &$responseAssertion) {
            $i++;
            $requestAssertion = $request;
            $responseAssertion = $response;
        });

        $this->connection
            ->expects($this->once())
            ->method('getRemoteAddress')
            ->willReturn('127.0.0.1');

        $this->socket->emit('connection', array($this->connection));

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
        $server = new Server($this->socket);
        $server->on('request', function (Request $request, Response $response) {
            $response->writeHead();
            $response->end();
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->will(
                $this->returnCallback(
                    function ($data) use (&$buffer) {
                        $buffer .= $data;
                    }
                )
            );

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));

        $this->assertContains("\r\nX-Powered-By: React/alpha\r\n", $buffer);
    }

    public function testParserErrorEmitted()
    {
        $error = null;
        $server = new Server($this->socket);
        $server->on('headers', $this->expectCallableNever());
        $server->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: example.com\r\nConnection: close\r\nX-DATA: ";
        $data .= str_repeat('A', 4097 - strlen($data)) . "\r\n\r\n";
        $this->connection->emit('data', array($data));

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
