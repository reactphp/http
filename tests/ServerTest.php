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

    public function testRequestPauseWillbeForwardedToConnection()
    {
        $server = new Server($this->socket);
        $server->on('request', function (Request $request) {
            $request->pause();
        });

        $this->connection->expects($this->once())->method('pause');
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestResumeWillbeForwardedToConnection()
    {
        $server = new Server($this->socket);
        $server->on('request', function (Request $request) {
            $request->resume();
        });

        $this->connection->expects($this->once())->method('resume');
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
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

    public function testBodyDataWillBeSendViaRequestEvent()
    {
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableNever();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
        });

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";
        $data .= "hello";

        $this->connection->emit('data', array($data));
    }

    public function testChunkedEncodedRequestWillBeParsedForRequestEvent()
    {
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
        });

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testChunkedEncodedRequestAdditionalDataWontBeEmitted()
    {
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
        });

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";
        $data .= "0\r\n\r\n";
        $data .= "2\r\nhi\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testEmptyChunkedEncodedRequest()
    {
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
        });

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testOneChunkWillBeEmittedDelayed()
    {
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
        });

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhel";

        $this->connection->emit('data', array($data));

        $data = "lo\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testEmitTwoChunksDelayed()
    {
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableConsecutive(2, array('hello', 'world'));
        $endEvent = $this->expectCallableOnce();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
        });

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";

        $this->connection->emit('data', array($data));

        $data = "5\r\nworld\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', array($data));
    }

    /**
     * All transfer-coding names are case-insensitive according to:
     * https://tools.ietf.org/html/rfc7230#section-4
     */
    public function testChunkedIsUpperCase()
    {
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
        });

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: CHUNKED\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', array($data));
    }

    /**
     * All transfer-coding names are case-insensitive according to:
     * https://tools.ietf.org/html/rfc7230#section-4
     */
    public function testChunkedIsMixedUpperAndLowerCase()
    {
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
        });

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: CHunKeD\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', array($data));
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
