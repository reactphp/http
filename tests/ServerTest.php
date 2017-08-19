<?php

namespace React\Tests\Http;

use React\Http\Server;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use React\Stream\ThroughStream;
use React\Promise\Promise;

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
                    'getLocalAddress',
                    'pipe'
                )
            )
            ->getMock();

        $this->connection->method('isWritable')->willReturn(true);
        $this->connection->method('isReadable')->willReturn(true);

        $this->socket = new SocketServerStub();
    }

    public function testRequestEventWillNotBeEmittedForIncompleteHeaders()
    {
        $server = new Server($this->expectCallableNever());

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = '';
        $data .= "GET / HTTP/1.1\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testRequestEventIsEmitted()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestEvent()
    {
        $i = 0;
        $requestAssertion = null;
        $server = new Server(function (ServerRequestInterface $request) use (&$i, &$requestAssertion) {
            $i++;
            $requestAssertion = $request;

            return \React\Promise\resolve(new Response());
        });

        $this->connection
            ->expects($this->any())
            ->method('getRemoteAddress')
            ->willReturn('127.0.0.1:8080');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));

        $serverParams = $requestAssertion->getServerParams();

        $this->assertSame(1, $i);
        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('/', $requestAssertion->getRequestTarget());
        $this->assertSame('/', $requestAssertion->getUri()->getPath());
        $this->assertSame(array(), $requestAssertion->getQueryParams());
        $this->assertSame('http://example.com/', (string)$requestAssertion->getUri());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
        $this->assertSame('127.0.0.1', $serverParams['REMOTE_ADDR']);
    }

    public function testRequestGetWithHostAndCustomPort()
    {
        $requestAssertion = null;
        $server = new Server(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: example.com:8080\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('/', $requestAssertion->getRequestTarget());
        $this->assertSame('/', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com:8080/', (string)$requestAssertion->getUri());
        $this->assertSame(8080, $requestAssertion->getUri()->getPort());
        $this->assertSame('example.com:8080', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestGetWithHostAndHttpsPort()
    {
        $requestAssertion = null;
        $server = new Server(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: example.com:443\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('/', $requestAssertion->getRequestTarget());
        $this->assertSame('/', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com:443/', (string)$requestAssertion->getUri());
        $this->assertSame(443, $requestAssertion->getUri()->getPort());
        $this->assertSame('example.com:443', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestGetWithHostAndDefaultPortWillBeIgnored()
    {
        $requestAssertion = null;
        $server = new Server(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: example.com:80\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('/', $requestAssertion->getRequestTarget());
        $this->assertSame('/', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com/', (string)$requestAssertion->getUri());
        $this->assertSame(null, $requestAssertion->getUri()->getPort());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestOptionsAsterisk()
    {
        $requestAssertion = null;
        $server = new Server(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "OPTIONS * HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('OPTIONS', $requestAssertion->getMethod());
        $this->assertSame('*', $requestAssertion->getRequestTarget());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com', (string)$requestAssertion->getUri());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestNonOptionsWithAsteriskRequestTargetWillReject()
    {
        $server = new Server($this->expectCallableNever());
        $server->on('error', $this->expectCallableOnce());

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET * HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testRequestConnectAuthorityForm()
    {
        $requestAssertion = null;
        $server = new Server(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "CONNECT example.com:443 HTTP/1.1\r\nHost: example.com:443\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('CONNECT', $requestAssertion->getMethod());
        $this->assertSame('example.com:443', $requestAssertion->getRequestTarget());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com:443', (string)$requestAssertion->getUri());
        $this->assertSame(443, $requestAssertion->getUri()->getPort());
        $this->assertSame('example.com:443', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestConnectWithoutHostWillBeAdded()
    {
        $requestAssertion = null;
        $server = new Server(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "CONNECT example.com:443 HTTP/1.1\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('CONNECT', $requestAssertion->getMethod());
        $this->assertSame('example.com:443', $requestAssertion->getRequestTarget());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com:443', (string)$requestAssertion->getUri());
        $this->assertSame(443, $requestAssertion->getUri()->getPort());
        $this->assertSame('example.com:443', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestConnectAuthorityFormWithDefaultPortWillBeIgnored()
    {
        $requestAssertion = null;
        $server = new Server(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('CONNECT', $requestAssertion->getMethod());
        $this->assertSame('example.com:80', $requestAssertion->getRequestTarget());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com', (string)$requestAssertion->getUri());
        $this->assertSame(null, $requestAssertion->getUri()->getPort());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestConnectAuthorityFormNonMatchingHostWillBeOverwritten()
    {
        $requestAssertion = null;
        $server = new Server(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "CONNECT example.com:80 HTTP/1.1\r\nHost: other.example.org\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('CONNECT', $requestAssertion->getMethod());
        $this->assertSame('example.com:80', $requestAssertion->getRequestTarget());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com', (string)$requestAssertion->getUri());
        $this->assertSame(null, $requestAssertion->getUri()->getPort());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestConnectOriginFormRequestTargetWillReject()
    {
        $server = new Server($this->expectCallableNever());
        $server->on('error', $this->expectCallableOnce());

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "CONNECT / HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testRequestNonConnectWithAuthorityRequestTargetWillReject()
    {
        $server = new Server($this->expectCallableNever());
        $server->on('error', $this->expectCallableOnce());

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET example.com:80 HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testRequestWithoutHostEventUsesSocketAddress()
    {
        $requestAssertion = null;

        $server = new Server(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $this->connection
            ->expects($this->any())
            ->method('getLocalAddress')
            ->willReturn('127.0.0.1:80');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET /test HTTP/1.0\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('/test', $requestAssertion->getRequestTarget());
        $this->assertEquals('http://127.0.0.1/test', $requestAssertion->getUri());
        $this->assertSame('/test', $requestAssertion->getUri()->getPath());
    }

    public function testRequestAbsoluteEvent()
    {
        $requestAssertion = null;

        $server = new Server(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET http://example.com/test HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('http://example.com/test', $requestAssertion->getRequestTarget());
        $this->assertEquals('http://example.com/test', $requestAssertion->getUri());
        $this->assertSame('/test', $requestAssertion->getUri()->getPath());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestAbsoluteAddsMissingHostEvent()
    {
        $requestAssertion = null;

        $server = new Server(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });
        $server->on('error', 'printf');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET http://example.com:8080/test HTTP/1.0\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('http://example.com:8080/test', $requestAssertion->getRequestTarget());
        $this->assertEquals('http://example.com:8080/test', $requestAssertion->getUri());
        $this->assertSame('/test', $requestAssertion->getUri()->getPath());
        $this->assertSame('example.com:8080', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestAbsoluteNonMatchingHostWillBeOverwritten()
    {
        $requestAssertion = null;

        $server = new Server(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET http://example.com/test HTTP/1.1\r\nHost: other.example.org\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('http://example.com/test', $requestAssertion->getRequestTarget());
        $this->assertEquals('http://example.com/test', $requestAssertion->getUri());
        $this->assertSame('/test', $requestAssertion->getUri()->getPath());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestOptionsAsteriskEvent()
    {
        $requestAssertion = null;

        $server = new Server(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "OPTIONS * HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('OPTIONS', $requestAssertion->getMethod());
        $this->assertSame('*', $requestAssertion->getRequestTarget());
        $this->assertEquals('http://example.com', $requestAssertion->getUri());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestOptionsAbsoluteEvent()
    {
        $requestAssertion = null;

        $server = new Server(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "OPTIONS http://example.com HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('OPTIONS', $requestAssertion->getMethod());
        $this->assertSame('http://example.com', $requestAssertion->getRequestTarget());
        $this->assertEquals('http://example.com', $requestAssertion->getUri());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestPauseWillbeForwardedToConnection()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            $request->getBody()->pause();
            return new Response();
        });

        $this->connection->expects($this->once())->method('pause');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testRequestResumeWillbeForwardedToConnection()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            $request->getBody()->resume();
            return new Response();
        });

        $this->connection->expects($this->once())->method('resume');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestCloseWillPauseConnection()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            $request->getBody()->close();
            return new Response();
        });

        $this->connection->expects($this->once())->method('pause');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestPauseAfterCloseWillNotBeForwarded()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            $request->getBody()->close();
            $request->getBody()->pause();#

            return new Response();
        });

        $this->connection->expects($this->once())->method('pause');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestResumeAfterCloseWillNotBeForwarded()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            $request->getBody()->close();
            $request->getBody()->resume();

            return new Response();
        });

        $this->connection->expects($this->once())->method('pause');
        $this->connection->expects($this->never())->method('resume');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestEventWithoutBodyWillNotEmitData()
    {
        $never = $this->expectCallableNever();

        $server = new Server(function (ServerRequestInterface $request) use ($never) {
            $request->getBody()->on('data', $never);

            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestEventWithSecondDataEventWillEmitBodyData()
    {
        $once = $this->expectCallableOnceWith('incomplete');

        $server = new Server(function (ServerRequestInterface $request) use ($once) {
            $request->getBody()->on('data', $once);

            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = '';
        $data .= "POST / HTTP/1.1\r\n";
        $data .= "Host: localhost\r\n";
        $data .= "Content-Length: 100\r\n";
        $data .= "\r\n";
        $data .= "incomplete";
        $this->connection->emit('data', array($data));
    }

    public function testRequestEventWithPartialBodyWillEmitData()
    {
        $once = $this->expectCallableOnceWith('incomplete');

        $server = new Server(function (ServerRequestInterface $request) use ($once) {
            $request->getBody()->on('data', $once);

            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = '';
        $data .= "POST / HTTP/1.1\r\n";
        $data .= "Host: localhost\r\n";
        $data .= "Content-Length: 100\r\n";
        $data .= "\r\n";
        $this->connection->emit('data', array($data));

        $data = '';
        $data .= "incomplete";
        $this->connection->emit('data', array($data));
    }

    public function testResponseContainsPoweredByHeader()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return new Response();
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));

        $this->assertContains("\r\nX-Powered-By: React/alpha\r\n", $buffer);
    }

    public function testPendingPromiseWillNotSendAnything()
    {
        $never = $this->expectCallableNever();

        $server = new Server(function (ServerRequestInterface $request) use ($never) {
            return new Promise(function () { }, $never);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));

        $this->assertEquals('', $buffer);
    }

    public function testPendingPromiseWillBeCancelledIfConnectionCloses()
    {
        $once = $this->expectCallableOnce();

        $server = new Server(function (ServerRequestInterface $request) use ($once) {
            return new Promise(function () { }, $once);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
        $this->connection->emit('close');

        $this->assertEquals('', $buffer);
    }

    public function testStreamAlreadyClosedWillSendEmptyBodyChunkedEncoded()
    {
        $stream = new ThroughStream();
        $stream->close();

        $server = new Server(function (ServerRequestInterface $request) use ($stream) {
            return new Response(200, array(), $stream);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertStringEndsWith("\r\n\r\n0\r\n\r\n", $buffer);
    }

    public function testResponseStreamEndingWillSendEmptyBodyChunkedEncoded()
    {
        $stream = new ThroughStream();

        $server = new Server(function (ServerRequestInterface $request) use ($stream) {
            return new Response(200, array(), $stream);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', array($data));

        $stream->end();

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertStringEndsWith("\r\n\r\n0\r\n\r\n", $buffer);
    }

    public function testResponseStreamAlreadyClosedWillSendEmptyBodyPlainHttp10()
    {
        $stream = new ThroughStream();
        $stream->close();

        $server = new Server(function (ServerRequestInterface $request) use ($stream) {
            return new Response(200, array(), $stream);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertStringStartsWith("HTTP/1.0 200 OK\r\n", $buffer);
        $this->assertStringEndsWith("\r\n\r\n", $buffer);
    }

    public function testResponseStreamWillBeClosedIfConnectionIsAlreadyClosed()
    {
        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $server = new Server(function (ServerRequestInterface $request) use ($stream) {
            return new Response(200, array(), $stream);
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
                    'getLocalAddress',
                    'pipe'
                )
            )
            ->getMock();

        $this->connection->expects($this->once())->method('isWritable')->willReturn(false);
        $this->connection->expects($this->never())->method('write');
        $this->connection->expects($this->never())->method('write');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testResponseStreamWillBeClosedIfConnectionEmitsCloseEvent()
    {
        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $server = new Server(function (ServerRequestInterface $request) use ($stream) {
            return new Response(200, array(), $stream);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
        $this->connection->emit('close');
    }

    public function testUpgradeInResponseCanBeUsedToAdvertisePossibleUpgrade()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return new Response(200, array('date' => '', 'x-powered-by' => '', 'Upgrade' => 'demo'), 'foo');
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertEquals("HTTP/1.1 200 OK\r\nUpgrade: demo\r\nContent-Length: 3\r\nConnection: close\r\n\r\nfoo", $buffer);
    }

    public function testUpgradeWishInRequestCanBeIgnoredByReturningNormalResponse()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return new Response(200, array('date' => '', 'x-powered-by' => ''), 'foo');
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nUpgrade: demo\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertEquals("HTTP/1.1 200 OK\r\nContent-Length: 3\r\nConnection: close\r\n\r\nfoo", $buffer);
    }

    public function testUpgradeSwitchingProtocolIncludesConnectionUpgradeHeaderWithoutContentLength()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return new Response(101, array('date' => '', 'x-powered-by' => '', 'Upgrade' => 'demo'), 'foo');
        });

        $server->on('error', 'printf');

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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nUpgrade: demo\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertEquals("HTTP/1.1 101 Switching Protocols\r\nUpgrade: demo\r\nConnection: upgrade\r\n\r\nfoo", $buffer);
    }

    public function testUpgradeSwitchingProtocolWithStreamWillPipeDataToConnection()
    {
        $stream = new ThroughStream();

        $server = new Server(function (ServerRequestInterface $request) use ($stream) {
            return new Response(101, array('date' => '', 'x-powered-by' => '', 'Upgrade' => 'demo'), $stream);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nUpgrade: demo\r\n\r\n";
        $this->connection->emit('data', array($data));

        $stream->write('hello');
        $stream->write('world');

        $this->assertEquals("HTTP/1.1 101 Switching Protocols\r\nUpgrade: demo\r\nConnection: upgrade\r\n\r\nhelloworld", $buffer);
    }

    public function testConnectResponseStreamWillPipeDataToConnection()
    {
        $stream = new ThroughStream();

        $server = new Server(function (ServerRequestInterface $request) use ($stream) {
            return new Response(200, array(), $stream);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\n\r\n";
        $this->connection->emit('data', array($data));

        $stream->write('hello');
        $stream->write('world');

        $this->assertStringEndsWith("\r\n\r\nhelloworld", $buffer);
    }


    public function testConnectResponseStreamWillPipeDataFromConnection()
    {
        $stream = new ThroughStream();

        $server = new Server(function (ServerRequestInterface $request) use ($stream) {
            return new Response(200, array(), $stream);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('pipe')->with($stream);

        $data = "CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\n\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testResponseContainsSameRequestProtocolVersionAndChunkedBodyForHttp11()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            $response = new Response(200, array(), 'bye');
            return \React\Promise\resolve($response);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContains("bye", $buffer);
    }

    public function testResponseContainsSameRequestProtocolVersionAndRawBodyForHttp10()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            $response = new Response(200, array(), 'bye');
            return \React\Promise\resolve($response);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.0 200 OK\r\n", $buffer);
        $this->assertContains("\r\n\r\n", $buffer);
        $this->assertContains("bye", $buffer);
    }

    public function testResponseContainsNoResponseBodyForHeadRequest()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return new Response(200, array(), 'bye');
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "HEAD / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertNotContains("bye", $buffer);
    }

    public function testResponseContainsNoResponseBodyAndNoContentLengthForNoContentStatus()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return new Response(204, array(), 'bye');
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 204 No Content\r\n", $buffer);
        $this->assertNotContains("\r\n\Content-Length: 3\r\n", $buffer);
        $this->assertNotContains("bye", $buffer);
    }

    public function testResponseContainsNoResponseBodyForNotModifiedStatus()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return new Response(304, array(), 'bye');
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 304 Not Modified\r\n", $buffer);
        $this->assertContains("\r\nContent-Length: 3\r\n", $buffer);
        $this->assertNotContains("bye", $buffer);
    }

    public function testRequestInvalidHttpProtocolVersionWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = new Server($this->expectCallableNever());
        $server->on('error', function ($message) use (&$error) {
            $error = $message;
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.2\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('InvalidArgumentException', $error);

        $this->assertContains("HTTP/1.1 505 HTTP Version not supported\r\n", $buffer);
        $this->assertContains("\r\n\r\n", $buffer);
        $this->assertContains("Error 505: HTTP Version not supported", $buffer);
    }

    public function testRequestOverflowWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = new Server($this->expectCallableNever());
        $server->on('error', function ($message) use (&$error) {
            $error = $message;
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: example.com\r\nConnection: close\r\nX-DATA: ";
        $data .= str_repeat('A', 4097 - strlen($data)) . "\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('OverflowException', $error);

        $this->assertContains("HTTP/1.1 431 Request Header Fields Too Large\r\n", $buffer);
        $this->assertContains("\r\n\r\nError 431: Request Header Fields Too Large", $buffer);
    }

    public function testRequestInvalidWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = new Server($this->expectCallableNever());
        $server->on('error', function ($message) use (&$error) {
            $error = $message;
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "bad request\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('InvalidArgumentException', $error);

        $this->assertContains("HTTP/1.1 400 Bad Request\r\n", $buffer);
        $this->assertContains("\r\n\r\nError 400: Bad Request", $buffer);
    }

    public function testBodyDataWillBeSendViaRequestEvent()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
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
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();
        $requestValidation = null;

        $server = new Server(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent, &$requestValidation) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
            $requestValidation = $request;

            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', array($data));

        $this->assertFalse($requestValidation->hasHeader('Transfer-Encoding'));
    }

    public function testChunkedEncodedRequestAdditionalDataWontBeEmitted()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
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
        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testChunkedIsUpperCase()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
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

    public function testChunkedIsMixedUpperAndLowerCase()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
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

    public function testRequestWithMalformedHostWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = new Server($this->expectCallableNever());
        $server->on('error', function ($message) use (&$error) {
            $error = $message;
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: ///\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('InvalidArgumentException', $error);

        $this->assertContains("HTTP/1.1 400 Bad Request\r\n", $buffer);
        $this->assertContains("\r\n\r\nError 400: Bad Request", $buffer);
    }

    public function testRequestWithInvalidHostUriComponentsWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = new Server($this->expectCallableNever());
        $server->on('error', function ($message) use (&$error) {
            $error = $message;
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: localhost:80/test\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('InvalidArgumentException', $error);

        $this->assertContains("HTTP/1.1 400 Bad Request\r\n", $buffer);
        $this->assertContains("\r\n\r\nError 400: Bad Request", $buffer);
    }

    public function testWontEmitFurtherDataWhenContentLengthIsReached()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";
        $data .= "hello";
        $data .= "world";

        $this->connection->emit('data', array($data));
    }

    public function testWontEmitFurtherDataWhenContentLengthIsReachedSplitted()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();


        $server = new Server(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";
        $data .= "hello";

        $this->connection->emit('data', array($data));

        $data = "world";

        $this->connection->emit('data', array($data));
    }

    public function testContentLengthContainsZeroWillEmitEndEvent()
    {

        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 0\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testContentLengthContainsZeroWillEmitEndEventAdditionalDataWillBeIgnored()
    {
        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 0\r\n";
        $data .= "\r\n";
        $data .= "hello";

        $this->connection->emit('data', array($data));
    }

    public function testContentLengthContainsZeroWillEmitEndEventAdditionalDataWillBeIgnoredSplitted()
    {
        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 0\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));

        $data = "hello";

        $this->connection->emit('data', array($data));
    }

    public function testContentLengthWillBeIgnoredIfTransferEncodingIsSet()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $requestValidation = null;
        $server = new Server(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent, &$requestValidation) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
            $requestValidation = $request;

            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 4\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));

        $data = "5\r\nhello\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', array($data));

        $this->assertFalse($requestValidation->hasHeader('Content-Length'));
        $this->assertFalse($requestValidation->hasHeader('Transfer-Encoding'));
    }

    public function testInvalidContentLengthWillBeIgnoreddIfTransferEncodingIsSet()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $requestValidation = null;
        $server = new Server(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent, &$requestValidation) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
            $requestValidation = $request;

            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        // this is valid behavior according to: https://www.ietf.org/rfc/rfc2616.txt chapter 4.4
        $data .= "Content-Length: hello world\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));

        $data = "5\r\nhello\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', array($data));

        $this->assertFalse($requestValidation->hasHeader('Content-Length'));
        $this->assertFalse($requestValidation->hasHeader('Transfer-Encoding'));
    }

    public function testNonIntegerContentLengthValueWillLeadToError()
    {
        $error = null;
        $server = new Server($this->expectCallableNever());
        $server->on('error', function ($message) use (&$error) {
            $error = $message;
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: bla\r\n";
        $data .= "\r\n";
        $data .= "hello";

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 400 Bad Request\r\n", $buffer);
        $this->assertContains("\r\n\r\nError 400: Bad Request", $buffer);
        $this->assertInstanceOf('InvalidArgumentException', $error);
    }

    public function testNonIntegerContentLengthValueWillLeadToErrorWithNoBodyForHeadRequest()
    {
        $error = null;
        $server = new Server($this->expectCallableNever());
        $server->on('error', function ($message) use (&$error) {
            $error = $message;
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "HEAD / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: bla\r\n";
        $data .= "\r\n";
        $data .= "hello";

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 400 Bad Request\r\n", $buffer);
        $this->assertNotContains("\r\n\r\nError 400: Bad Request", $buffer);
        $this->assertInstanceOf('InvalidArgumentException', $error);
    }

    public function testMultipleIntegerInContentLengthWillLeadToError()
    {
        $error = null;
        $server = new Server($this->expectCallableNever());
        $server->on('error', function ($message) use (&$error) {
            $error = $message;
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5, 3, 4\r\n";
        $data .= "\r\n";
        $data .= "hello";

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 400 Bad Request\r\n", $buffer);
        $this->assertContains("\r\n\r\nError 400: Bad Request", $buffer);
        $this->assertInstanceOf('InvalidArgumentException', $error);
    }

    public function testInvalidChunkHeaderResultsInErrorOnRequestStream()
    {
        $errorEvent = $this->expectCallableOnceWith($this->isInstanceOf('Exception'));
        $server = new Server(function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->once())->method('pause');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "hello\r\hello\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testTooLongChunkHeaderResultsInErrorOnRequestStream()
    {
        $errorEvent = $this->expectCallableOnceWith($this->isInstanceOf('Exception'));
        $server = new Server(function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->once())->method('pause');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        for ($i = 0; $i < 1025; $i++) {
            $data .= 'a';
        }

        $this->connection->emit('data', array($data));
    }

    public function testTooLongChunkBodyResultsInErrorOnRequestStream()
    {
        $errorEvent = $this->expectCallableOnceWith($this->isInstanceOf('Exception'));
        $server = new Server(function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->once())->method('pause');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello world\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testUnexpectedEndOfConnectionWillResultsInErrorOnRequestStream()
    {
        $errorEvent = $this->expectCallableOnceWith($this->isInstanceOf('Exception'));
        $server = new Server(function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->once())->method('pause');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";

        $this->connection->emit('data', array($data));
        $this->connection->emit('end');
    }

    public function testErrorInChunkedDecoderNeverClosesConnection()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->once())->method('pause');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "hello\r\nhello\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testErrorInLengthLimitedStreamNeverClosesConnection()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->once())->method('pause');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";
        $data .= "hello";

        $this->connection->emit('data', array($data));
        $this->connection->emit('end');
    }

    public function testCloseRequestWillPauseConnection()
    {
        $server = new Server(function ($request) {
            $request->getBody()->close();
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->once())->method('pause');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testEndEventWillBeEmittedOnSimpleRequest()
    {
        $dataEvent = $this->expectCallableNever();
        $closeEvent = $this->expectCallableOnce();
        $endEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server(function ($request) use ($dataEvent, $closeEvent, $endEvent, $errorEvent){
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->once())->method('pause');
        $this->connection->expects($this->never())->method('close');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));
    }

    public function testRequestWithoutDefinedLengthWillIgnoreDataEvent()
    {
        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $data .= "hello world";

        $this->connection->emit('data', array($data));
    }

    public function testResponseWillBeChunkDecodedByDefault()
    {
        $stream = new ThroughStream();
        $server = new Server(function (ServerRequestInterface $request) use ($stream) {
            $response = new Response(200, array(), $stream);
            return \React\Promise\resolve($response);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));
        $stream->emit('data', array('hello'));

        $this->assertContains("Transfer-Encoding: chunked", $buffer);
        $this->assertContains("hello", $buffer);
    }

    public function testContentLengthWillBeRemovedForResponseStream()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            $response = new Response(
                200,
                array(
                    'Content-Length' => 5,
                    'Transfer-Encoding' => 'chunked'
                ),
                'hello'
            );

            return \React\Promise\resolve($response);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertNotContains("Transfer-Encoding: chunked", $buffer);
        $this->assertContains("Content-Length: 5", $buffer);
        $this->assertContains("hello", $buffer);
    }

    public function testOnlyAllowChunkedEncoding()
    {
        $stream = new ThroughStream();
        $server = new Server(function (ServerRequestInterface $request) use ($stream) {
            $response = new Response(
                200,
                array(
                    'Transfer-Encoding' => 'custom'
                ),
                $stream
            );

            return \React\Promise\resolve($response);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));
        $stream->emit('data', array('hello'));

        $this->assertContains('Transfer-Encoding: chunked', $buffer);
        $this->assertNotContains('Transfer-Encoding: custom', $buffer);
        $this->assertContains("5\r\nhello\r\n", $buffer);
    }

    public function testDateHeaderWillBeAddedWhenNoneIsGiven()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return \React\Promise\resolve(new Response());
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContains("Date:", $buffer);
        $this->assertContains("\r\n\r\n", $buffer);
    }

    public function testAddCustomDateHeader()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            $response = new Response(200, array("Date" => "Tue, 15 Nov 1994 08:12:31 GMT"));
            return \React\Promise\resolve($response);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContains("Date: Tue, 15 Nov 1994 08:12:31 GMT\r\n", $buffer);
        $this->assertContains("\r\n\r\n", $buffer);
    }

    public function testRemoveDateHeader()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            $response = new Response(200, array('Date' => ''));
            return \React\Promise\resolve($response);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertNotContains("Date:", $buffer);
        $this->assertContains("\r\n\r\n", $buffer);
    }

    public function testOnlyChunkedEncodingIsAllowedForTransferEncoding()
    {
        $error = null;

        $server = new Server($this->expectCallableNever());
        $server->on('error', function ($exception) use (&$error) {
            $error = $exception;
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: custom\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 501 Not Implemented\r\n", $buffer);
        $this->assertContains("\r\n\r\nError 501: Not Implemented", $buffer);
        $this->assertInstanceOf('InvalidArgumentException', $error);
    }

    public function testOnlyChunkedEncodingIsAllowedForTransferEncodingWithHttp10()
    {
        $error = null;

        $server = new Server($this->expectCallableNever());
        $server->on('error', function ($exception) use (&$error) {
            $error = $exception;
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n";
        $data .= "Transfer-Encoding: custom\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.0 501 Not Implemented\r\n", $buffer);
        $this->assertContains("\r\n\r\nError 501: Not Implemented", $buffer);
        $this->assertInstanceOf('InvalidArgumentException', $error);
    }

    public function test100ContinueRequestWillBeHandled()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return \React\Promise\resolve(new Response());
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Expect: 100-continue\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));
        $this->assertContains("HTTP/1.1 100 Continue\r\n", $buffer);
        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
    }

    public function testContinueWontBeSendForHttp10()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return \React\Promise\resolve(new Response());
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n";
        $data .= "Expect: 100-continue\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));
        $this->assertContains("HTTP/1.0 200 OK\r\n", $buffer);
        $this->assertNotContains("HTTP/1.1 100 Continue\r\n\r\n", $buffer);
    }

    public function testContinueWithLaterResponse()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return \React\Promise\resolve(new Response());
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Expect: 100-continue\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 100 Continue\r\n\r\n", $buffer);
        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidCallbackFunctionLeadsToException()
    {
        $server = new Server('invalid');
    }

    public function testHttpBodyStreamAsBodyWillStreamData()
    {
        $input = new ThroughStream();

        $server = new Server(function (ServerRequestInterface $request) use ($input) {
            $response = new Response(200, array(), $input);
            return \React\Promise\resolve($response);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));
        $input->emit('data', array('1'));
        $input->emit('data', array('23'));

        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContains("\r\n\r\n", $buffer);
        $this->assertContains("1\r\n1\r\n", $buffer);
        $this->assertContains("2\r\n23\r\n", $buffer);
    }

    public function testHttpBodyStreamWithContentLengthWillStreamTillLength()
    {
        $input = new ThroughStream();

        $server = new Server(function (ServerRequestInterface $request) use ($input) {
            $response = new Response(200, array('Content-Length' => 5), $input);
            return \React\Promise\resolve($response);
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));
        $input->emit('data', array('hel'));
        $input->emit('data', array('lo'));

        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContains("Content-Length: 5\r\n", $buffer);
        $this->assertNotContains("Transfer-Encoding", $buffer);
        $this->assertContains("\r\n\r\n", $buffer);
        $this->assertContains("hello", $buffer);
    }

    public function testCallbackFunctionReturnsPromise()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return \React\Promise\resolve(new Response());
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));
        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContains("\r\n\r\n", $buffer);
    }

    public function testReturnInvalidTypeWillResultInError()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return "invalid";
        });

        $exception = null;
        $server->on('error', function (\Exception $ex) use (&$exception) {
            $exception = $ex;
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertInstanceOf('RuntimeException', $exception);
    }

    public function testResolveWrongTypeInPromiseWillResultInError()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return \React\Promise\resolve("invalid");
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
    }

    public function testRejectedPromiseWillResultInErrorMessage()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return new Promise(function ($resolve, $reject) {
                $reject(new \Exception());
            });
        });
        $server->on('error', $this->expectCallableOnce());

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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
    }

    public function testExcpetionInCallbackWillResultInErrorMessage()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return new Promise(function ($resolve, $reject) {
                throw new \Exception('Bad call');
            });
        });
        $server->on('error', $this->expectCallableOnce());

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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
    }

    public function testHeaderWillAlwaysBeContentLengthForStringBody()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return new Response(200, array('Transfer-Encoding' => 'chunked'), 'hello');
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContains("Content-Length: 5\r\n", $buffer);
        $this->assertContains("hello", $buffer);

        $this->assertNotContains("Transfer-Encoding", $buffer);
    }

    public function testReturnRequestWillBeHandled()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return new Response();
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
    }

    public function testExceptionThrowInCallBackFunctionWillResultInErrorMessage()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            throw new \Exception('hello');
        });

        $exception = null;
        $server->on('error', function (\Exception $ex) use (&$exception) {
            $exception = $ex;
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RuntimeException', $exception);
        $this->assertContains("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertEquals('hello', $exception->getPrevious()->getMessage());
    }

    /**
     * @requires PHP 7
     */
    public function testThrowableThrowInCallBackFunctionWillResultInErrorMessage()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            throw new \Error('hello');
        });

        $exception = null;
        $server->on('error', function (\Exception $ex) use (&$exception) {
            $exception = $ex;
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        try {
            $this->connection->emit('data', array($data));
        } catch (\Error $e) {
            $this->markTestSkipped(
                'A \Throwable bubbled out of the request callback. ' .
                'This happened most probably due to react/promise:^1.0 being installed ' .
                'which does not support \Throwable.'
            );
        }

        $this->assertInstanceOf('RuntimeException', $exception);
        $this->assertContains("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertEquals('hello', $exception->getPrevious()->getMessage());
    }

    public function testRejectOfNonExceptionWillResultInErrorMessage()
    {
        $server = new Server(function (ServerRequestInterface $request) {
            return new Promise(function ($resolve, $reject) {
                $reject('Invalid type');
            });
        });

        $exception = null;
        $server->on('error', function (\Exception $ex) use (&$exception) {
            $exception = $ex;
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

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertInstanceOf('RuntimeException', $exception);
    }

    public function testServerRequestParams()
    {
        $requestValidation = null;
        $server = new Server(function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
            return new Response();
        });

        $this->connection
            ->expects($this->any())
            ->method('getRemoteAddress')
            ->willReturn('192.168.1.2:80');

        $this->connection
            ->expects($this->any())
            ->method('getLocalAddress')
            ->willReturn('127.0.0.1:8080');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $serverParams = $requestValidation->getServerParams();

        $this->assertEquals('127.0.0.1', $serverParams['SERVER_ADDR']);
        $this->assertEquals('8080', $serverParams['SERVER_PORT']);
        $this->assertEquals('192.168.1.2', $serverParams['REMOTE_ADDR']);
        $this->assertEquals('80', $serverParams['REMOTE_PORT']);
        $this->assertNotNull($serverParams['REQUEST_TIME']);
        $this->assertNotNull($serverParams['REQUEST_TIME_FLOAT']);
    }

    public function testQueryParametersWillBeAddedToRequest()
    {
        $requestValidation = null;
        $server = new Server(function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET /foo.php?hello=world&test=bar HTTP/1.0\r\n\r\n";

        $this->connection->emit('data', array($data));

        $queryParams = $requestValidation->getQueryParams();

        $this->assertEquals('world', $queryParams['hello']);
        $this->assertEquals('bar', $queryParams['test']);
    }

    public function testCookieWillBeAddedToServerRequest()
    {
        $requestValidation = null;
        $server = new Server(function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Cookie: hello=world\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));

        $this->assertEquals(array('hello' => 'world'), $requestValidation->getCookieParams());
    }

    public function testMultipleCookiesWontBeAddedToServerRequest()
    {
        $requestValidation = null;
        $server = new Server(function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Cookie: hello=world\r\n";
        $data .= "Cookie: test=failed\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));
        $this->assertEquals(array(), $requestValidation->getCookieParams());
    }

    public function testCookieWithSeparatorWillBeAddedToServerRequest()
    {
        $requestValidation = null;
        $server = new Server(function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
            return new Response();
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Cookie: hello=world; test=abc\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));
        $this->assertEquals(array('hello' => 'world', 'test' => 'abc'), $requestValidation->getCookieParams());
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
