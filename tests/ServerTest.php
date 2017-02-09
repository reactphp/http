<?php

namespace React\Tests\Http;

use React\Http\Request;
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
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf('OverflowException', $error);
        $this->connection->expects($this->never())->method('write');
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
