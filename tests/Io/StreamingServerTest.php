<?php

namespace React\Tests\Http\Io;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use React\EventLoop\Loop;
use React\Http\Io\RequestHeaderParser;
use React\Http\Io\StreamingServer;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\Promise;
use React\Socket\Connection;
use React\Stream\ThroughStream;
use React\Tests\Http\SocketServerStub;
use React\Tests\Http\TestCase;
use function React\Promise\resolve;

class StreamingServerTest extends TestCase
{
    private $connection;
    private $socket;

    /** @var ?int */
    private $called = null;

    /**
     * @before
     */
    public function setUpConnectionMockAndSocket()
    {
        $this->connection = $this->mockConnection();

        $this->socket = new SocketServerStub();
    }


    private function mockConnection(array $additionalMethods = [])
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->setMethods(array_merge(
                [
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
                ],
                $additionalMethods
            ))
            ->getMock();

        $connection->method('isWritable')->willReturn(true);
        $connection->method('isReadable')->willReturn(true);

        return $connection;
    }

    public function testRequestEventWillNotBeEmittedForIncompleteHeaders()
    {
        $server = new StreamingServer(Loop::get(), $this->expectCallableNever());

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = '';
        $data .= "GET / HTTP/1.1\r\n";
        $this->connection->emit('data', [$data]);
    }

    public function testRequestEventIsEmitted()
    {
        $server = new StreamingServer(Loop::get(), $this->expectCallableOnce());

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();
        $this->connection->emit('data', [$data]);
    }

    public function testRequestEventIsEmittedForArrayCallable()
    {
        $this->called = null;
        $server = new StreamingServer(Loop::get(), [$this, 'helperCallableOnce']);

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();
        $this->connection->emit('data', [$data]);

        $this->assertEquals(1, $this->called);
    }

    public function helperCallableOnce()
    {
        ++$this->called;
    }

    public function testRequestEvent()
    {
        $i = 0;
        $requestAssertion = null;
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$i, &$requestAssertion) {
            $i++;
            $requestAssertion = $request;
        });

        $this->connection
            ->expects($this->any())
            ->method('getRemoteAddress')
            ->willReturn('127.0.0.1:8080');

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();
        $this->connection->emit('data', [$data]);

        $serverParams = $requestAssertion->getServerParams();

        $this->assertSame(1, $i);
        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('/', $requestAssertion->getRequestTarget());
        $this->assertSame('/', $requestAssertion->getUri()->getPath());
        $this->assertSame([], $requestAssertion->getQueryParams());
        $this->assertSame('http://example.com/', (string)$requestAssertion->getUri());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
        $this->assertSame('127.0.0.1', $serverParams['REMOTE_ADDR']);
    }

    public function testRequestEventWithSingleRequestHandlerArray()
    {
        $i = 0;
        $requestAssertion = null;
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$i, &$requestAssertion) {
            $i++;
            $requestAssertion = $request;
        });

        $this->connection
            ->expects($this->any())
            ->method('getRemoteAddress')
            ->willReturn('127.0.0.1:8080');

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();
        $this->connection->emit('data', [$data]);

        $serverParams = $requestAssertion->getServerParams();

        $this->assertSame(1, $i);
        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('/', $requestAssertion->getRequestTarget());
        $this->assertSame('/', $requestAssertion->getUri()->getPath());
        $this->assertSame([], $requestAssertion->getQueryParams());
        $this->assertSame('http://example.com/', (string)$requestAssertion->getUri());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
        $this->assertSame('127.0.0.1', $serverParams['REMOTE_ADDR']);
    }

    public function testRequestGetWithHostAndCustomPort()
    {
        $requestAssertion = null;
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: example.com:8080\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
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
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: example.com:443\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
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
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('/', $requestAssertion->getRequestTarget());
        $this->assertSame('/', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com/', (string)$requestAssertion->getUri());
        $this->assertNull($requestAssertion->getUri()->getPort());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestGetHttp10WithoutHostWillBeIgnored()
    {
        $requestAssertion = null;
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.0\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('/', $requestAssertion->getRequestTarget());
        $this->assertSame('/', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://127.0.0.1/', (string)$requestAssertion->getUri());
        $this->assertNull($requestAssertion->getUri()->getPort());
        $this->assertEquals('1.0', $requestAssertion->getProtocolVersion());
        $this->assertSame('', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestGetHttp11WithoutHostWillReject()
    {
        $server = new StreamingServer(Loop::get(), 'var_dump');
        $server->on('error', $this->expectCallableOnce());

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n\r\n";
        $this->connection->emit('data', [$data]);
    }

    public function testRequestOptionsAsterisk()
    {
        $requestAssertion = null;
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "OPTIONS * HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
        $this->assertSame('OPTIONS', $requestAssertion->getMethod());
        $this->assertSame('*', $requestAssertion->getRequestTarget());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com', (string)$requestAssertion->getUri());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestNonOptionsWithAsteriskRequestTargetWillReject()
    {
        $server = new StreamingServer(Loop::get(), $this->expectCallableNever());
        $server->on('error', $this->expectCallableOnce());

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET * HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', [$data]);
    }

    public function testRequestConnectAuthorityForm()
    {
        $requestAssertion = null;
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "CONNECT example.com:443 HTTP/1.1\r\nHost: example.com:443\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
        $this->assertSame('CONNECT', $requestAssertion->getMethod());
        $this->assertSame('example.com:443', $requestAssertion->getRequestTarget());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com:443', (string)$requestAssertion->getUri());
        $this->assertSame(443, $requestAssertion->getUri()->getPort());
        $this->assertSame('example.com:443', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestConnectWithoutHostWillBePassesAsIs()
    {
        $requestAssertion = null;
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "CONNECT example.com:443 HTTP/1.1\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
        $this->assertSame('CONNECT', $requestAssertion->getMethod());
        $this->assertSame('example.com:443', $requestAssertion->getRequestTarget());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com:443', (string)$requestAssertion->getUri());
        $this->assertSame(443, $requestAssertion->getUri()->getPort());
        $this->assertFalse($requestAssertion->hasHeader('Host'));
    }

    public function testRequestConnectAuthorityFormWithDefaultPortWillBePassedAsIs()
    {
        $requestAssertion = null;
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
        $this->assertSame('CONNECT', $requestAssertion->getMethod());
        $this->assertSame('example.com:80', $requestAssertion->getRequestTarget());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com', (string)$requestAssertion->getUri());
        $this->assertNull($requestAssertion->getUri()->getPort());
        $this->assertSame('example.com:80', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestConnectAuthorityFormNonMatchingHostWillBePassedAsIs()
    {
        $requestAssertion = null;
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "CONNECT example.com:80 HTTP/1.1\r\nHost: other.example.org\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
        $this->assertSame('CONNECT', $requestAssertion->getMethod());
        $this->assertSame('example.com:80', $requestAssertion->getRequestTarget());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com', (string)$requestAssertion->getUri());
        $this->assertNull($requestAssertion->getUri()->getPort());
        $this->assertSame('other.example.org', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestConnectOriginFormRequestTargetWillReject()
    {
        $server = new StreamingServer(Loop::get(), $this->expectCallableNever());
        $server->on('error', $this->expectCallableOnce());

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "CONNECT / HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', [$data]);
    }

    public function testRequestNonConnectWithAuthorityRequestTargetWillReject()
    {
        $server = new StreamingServer(Loop::get(), $this->expectCallableNever());
        $server->on('error', $this->expectCallableOnce());

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET example.com:80 HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', [$data]);
    }

    public function testRequestWithoutHostEventUsesSocketAddress()
    {
        $requestAssertion = null;

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $this->connection
            ->expects($this->any())
            ->method('getLocalAddress')
            ->willReturn('127.0.0.1:80');

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET /test HTTP/1.0\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('/test', $requestAssertion->getRequestTarget());
        $this->assertEquals('http://127.0.0.1/test', $requestAssertion->getUri());
        $this->assertSame('/test', $requestAssertion->getUri()->getPath());
    }

    public function testRequestAbsoluteEvent()
    {
        $requestAssertion = null;

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET http://example.com/test HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('http://example.com/test', $requestAssertion->getRequestTarget());
        $this->assertEquals('http://example.com/test', $requestAssertion->getUri());
        $this->assertSame('/test', $requestAssertion->getUri()->getPath());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestAbsoluteNonMatchingHostWillBePassedAsIs()
    {
        $requestAssertion = null;

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET http://example.com/test HTTP/1.1\r\nHost: other.example.org\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('http://example.com/test', $requestAssertion->getRequestTarget());
        $this->assertEquals('http://example.com/test', $requestAssertion->getUri());
        $this->assertSame('/test', $requestAssertion->getUri()->getPath());
        $this->assertSame('other.example.org', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestAbsoluteWithoutHostWillReject()
    {
        $server = new StreamingServer(Loop::get(), $this->expectCallableNever());
        $server->on('error', $this->expectCallableOnce());

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET http://example.com:8080/test HTTP/1.1\r\n\r\n";
        $this->connection->emit('data', [$data]);
    }

    public function testRequestOptionsAsteriskEvent()
    {
        $requestAssertion = null;

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "OPTIONS * HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
        $this->assertSame('OPTIONS', $requestAssertion->getMethod());
        $this->assertSame('*', $requestAssertion->getRequestTarget());
        $this->assertEquals('http://example.com', $requestAssertion->getUri());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestOptionsAbsoluteEvent()
    {
        $requestAssertion = null;

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "OPTIONS http://example.com HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(ServerRequestInterface::class, $requestAssertion);
        $this->assertSame('OPTIONS', $requestAssertion->getMethod());
        $this->assertSame('http://example.com', $requestAssertion->getRequestTarget());
        $this->assertEquals('http://example.com', $requestAssertion->getUri());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestPauseWillBeForwardedToConnection()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            $request->getBody()->pause();
        });

        $this->connection->expects($this->once())->method('pause');

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', [$data]);
    }

    public function testRequestResumeWillBeForwardedToConnection()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            $request->getBody()->resume();
        });

        $this->connection->expects($this->once())->method('resume');

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', [$data]);
    }

    public function testRequestCloseWillNotCloseConnection()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            $request->getBody()->close();
        });

        $this->connection->expects($this->never())->method('close');

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();
        $this->connection->emit('data', [$data]);
    }

    public function testRequestPauseAfterCloseWillNotBeForwarded()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            $request->getBody()->close();
            $request->getBody()->pause();
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->never())->method('pause');

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();
        $this->connection->emit('data', [$data]);
    }

    public function testRequestResumeAfterCloseWillNotBeForwarded()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            $request->getBody()->close();
            $request->getBody()->resume();
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->never())->method('resume');

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();
        $this->connection->emit('data', [$data]);
    }

    public function testRequestEventWithoutBodyWillNotEmitData()
    {
        $never = $this->expectCallableNever();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($never) {
            $request->getBody()->on('data', $never);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();
        $this->connection->emit('data', [$data]);
    }

    public function testRequestEventWithSecondDataEventWillEmitBodyData()
    {
        $once = $this->expectCallableOnceWith('incomplete');

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($once) {
            $request->getBody()->on('data', $once);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = '';
        $data .= "POST / HTTP/1.1\r\n";
        $data .= "Host: localhost\r\n";
        $data .= "Content-Length: 100\r\n";
        $data .= "\r\n";
        $data .= "incomplete";
        $this->connection->emit('data', [$data]);
    }

    public function testRequestEventWithPartialBodyWillEmitData()
    {
        $once = $this->expectCallableOnceWith('incomplete');

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($once) {
            $request->getBody()->on('data', $once);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = '';
        $data .= "POST / HTTP/1.1\r\n";
        $data .= "Host: localhost\r\n";
        $data .= "Content-Length: 100\r\n";
        $data .= "\r\n";
        $this->connection->emit('data', [$data]);

        $data = '';
        $data .= "incomplete";
        $this->connection->emit('data', [$data]);
    }

    public function testResponseContainsServerHeader()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response();
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();
        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("\r\nServer: ReactPHP/1\r\n", $buffer);
    }

    public function testResponsePendingPromiseWillNotSendAnything()
    {
        $never = $this->expectCallableNever();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($never) {
            return new Promise(function () { }, $never);
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();
        $this->connection->emit('data', [$data]);

        $this->assertEquals('', $buffer);
    }

    public function testResponsePendingPromiseWillBeCancelledIfConnectionCloses()
    {
        $once = $this->expectCallableOnce();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($once) {
            return new Promise(function () { }, $once);
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();
        $this->connection->emit('data', [$data]);
        $this->connection->emit('close');

        $this->assertEquals('', $buffer);
    }

    public function testResponseBodyStreamAlreadyClosedWillSendEmptyBodyChunkedEncoded()
    {
        $stream = new ThroughStream();
        $stream->close();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                [],
                $stream
            );
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertStringEndsWith("\r\n\r\n0\r\n\r\n", $buffer);
    }

    public function testResponseBodyStreamEndingWillSendEmptyBodyChunkedEncoded()
    {
        $stream = new ThroughStream();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                [],
                $stream
            );
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $stream->end();

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertStringEndsWith("\r\n\r\n0\r\n\r\n", $buffer);
    }

    public function testResponseBodyStreamAlreadyClosedWillSendEmptyBodyPlainHttp10()
    {
        $stream = new ThroughStream();
        $stream->close();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                [],
                $stream
            );
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.0\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertStringStartsWith("HTTP/1.0 200 OK\r\n", $buffer);
        $this->assertStringEndsWith("\r\n\r\n", $buffer);
    }

    public function testResponseStreamWillBeClosedIfConnectionIsAlreadyClosed()
    {
        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                [],
                $stream
            );
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $this->connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
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
                ]
            )
            ->getMock();

        $this->connection->expects($this->once())->method('isWritable')->willReturn(false);
        $this->connection->expects($this->never())->method('write');
        $this->connection->expects($this->never())->method('write');

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();
        $this->connection->emit('data', [$data]);
    }

    public function testResponseBodyStreamWillBeClosedIfConnectionEmitsCloseEvent()
    {
        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                [],
                $stream
            );
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();
        $this->connection->emit('data', [$data]);
        $this->connection->emit('close');
    }

    public function testResponseUpgradeInResponseCanBeUsedToAdvertisePossibleUpgrade()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                200,
                [
                    'date' => '',
                    'server' => '',
                    'Upgrade' => 'demo'
                ],
                'foo'
            );
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertEquals("HTTP/1.1 200 OK\r\nUpgrade: demo\r\nContent-Length: 3\r\n\r\nfoo", $buffer);
    }

    public function testResponseUpgradeWishInRequestCanBeIgnoredByReturningNormalResponse()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                200,
                [
                    'date' => '',
                    'server' => ''
                ],
                'foo'
            );
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\nUpgrade: demo\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertEquals("HTTP/1.1 200 OK\r\nContent-Length: 3\r\n\r\nfoo", $buffer);
    }

    public function testResponseUpgradeSwitchingProtocolIncludesConnectionUpgradeHeaderWithoutContentLength()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                101,
                [
                    'date' => '',
                    'server' => '',
                    'Upgrade' => 'demo'
                ],
                'foo'
            );
        });

        $server->on('error', 'printf');

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\nUpgrade: demo\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertEquals("HTTP/1.1 101 Switching Protocols\r\nUpgrade: demo\r\nConnection: upgrade\r\n\r\nfoo", $buffer);
    }

    public function testResponseUpgradeSwitchingProtocolWithStreamWillPipeDataToConnection()
    {
        $stream = new ThroughStream();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                101,
                [
                    'date' => '',
                    'server' => '',
                    'Upgrade' => 'demo'
                ],
                $stream
            );
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\nUpgrade: demo\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $stream->write('hello');
        $stream->write('world');

        $this->assertEquals("HTTP/1.1 101 Switching Protocols\r\nUpgrade: demo\r\nConnection: upgrade\r\n\r\nhelloworld", $buffer);
    }

    public function testResponseConnectMethodStreamWillPipeDataToConnection()
    {
        $stream = new ThroughStream();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                [],
                $stream
            );
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $stream->write('hello');
        $stream->write('world');

        $this->assertStringEndsWith("\r\n\r\nhelloworld", $buffer);
    }


    public function testResponseConnectMethodStreamWillPipeDataFromConnection()
    {
        $stream = new ThroughStream();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                [],
                $stream
            );
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $this->connection->expects($this->once())->method('pipe')->with($stream);

        $data = "CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\n\r\n";
        $this->connection->emit('data', [$data]);
    }

    public function testResponseContainsSameRequestProtocolVersionAndChunkedBodyForHttp11()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                200,
                [],
                'bye'
            );
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertStringContainsString("bye", $buffer);
    }

    public function testResponseContainsSameRequestProtocolVersionAndRawBodyForHttp10()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                200,
                [],
                'bye'
            );
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.0\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.0 200 OK\r\n", $buffer);
        $this->assertStringContainsString("\r\n\r\n", $buffer);
        $this->assertStringContainsString("bye", $buffer);
    }

    public function testResponseContainsNoResponseBodyForHeadRequest()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                200,
                [],
                'bye'
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "HEAD / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertStringContainsString("\r\nContent-Length: 3\r\n", $buffer);
        $this->assertStringNotContainsString("bye", $buffer);
    }

    public function testResponseContainsNoResponseBodyForHeadRequestWithStreamingResponse()
    {
        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                ['Content-Length' => '3'],
                $stream
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "HEAD / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertStringContainsString("\r\nContent-Length: 3\r\n", $buffer);
    }

    public function testResponseContainsNoResponseBodyAndNoContentLengthForNoContentStatus()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                204,
                [],
                'bye'
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 204 No Content\r\n", $buffer);
        $this->assertStringNotContainsString("\r\nContent-Length: 3\r\n", $buffer);
        $this->assertStringNotContainsString("bye", $buffer);
    }

    public function testResponseContainsNoResponseBodyAndNoContentLengthForNoContentStatusResponseWithStreamingBody()
    {
        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                204,
                ['Content-Length' => '3'],
                $stream
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "HEAD / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 204 No Content\r\n", $buffer);
        $this->assertStringNotContainsString("\r\nContent-Length: 3\r\n", $buffer);
    }

    public function testResponseContainsNoContentLengthHeaderForNotModifiedStatus()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                304,
                [],
                ''
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 304 Not Modified\r\n", $buffer);
        $this->assertStringNotContainsString("\r\nContent-Length: 0\r\n", $buffer);
    }

    public function testResponseContainsExplicitContentLengthHeaderForNotModifiedStatus()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                304,
                ['Content-Length' => 3],
                ''
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 304 Not Modified\r\n", $buffer);
        $this->assertStringContainsString("\r\nContent-Length: 3\r\n", $buffer);
    }

    public function testResponseContainsExplicitContentLengthHeaderForHeadRequests()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                200,
                ['Content-Length' => 3],
                ''
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "HEAD / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertStringContainsString("\r\nContent-Length: 3\r\n", $buffer);
    }

    public function testResponseContainsNoResponseBodyForNotModifiedStatus()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                304,
                [],
                'bye'
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 304 Not Modified\r\n", $buffer);
        $this->assertStringContainsString("\r\nContent-Length: 3\r\n", $buffer);
        $this->assertStringNotContainsString("bye", $buffer);
    }

    public function testResponseContainsNoResponseBodyForNotModifiedStatusWithStreamingBody()
    {
        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                304,
                ['Content-Length' => '3'],
                $stream
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 304 Not Modified\r\n", $buffer);
        $this->assertStringContainsString("\r\nContent-Length: 3\r\n", $buffer);
    }

    public function testRequestInvalidHttpProtocolVersionWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = new StreamingServer(Loop::get(), $this->expectCallableNever());
        $server->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.2\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(\InvalidArgumentException::class, $error);

        $this->assertStringContainsString("HTTP/1.1 505 HTTP Version Not Supported\r\n", $buffer);
        $this->assertStringContainsString("\r\n\r\n", $buffer);
        $this->assertStringContainsString("Error 505: HTTP Version Not Supported", $buffer);
    }

    public function testRequestOverflowWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = new StreamingServer(Loop::get(), $this->expectCallableNever());
        $server->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: example.com\r\nConnection: close\r\nX-DATA: ";
        $data .= str_repeat('A', 8193 - strlen($data)) . "\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(\OverflowException::class, $error);

        $this->assertStringContainsString("HTTP/1.1 431 Request Header Fields Too Large\r\n", $buffer);
        $this->assertStringContainsString("\r\n\r\nError 431: Request Header Fields Too Large", $buffer);
    }

    public function testRequestInvalidWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = new StreamingServer(Loop::get(), $this->expectCallableNever());
        $server->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $buffer = '';

        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "bad request\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(\InvalidArgumentException::class, $error);

        $this->assertStringContainsString("HTTP/1.1 400 Bad Request\r\n", $buffer);
        $this->assertStringContainsString("\r\n\r\nError 400: Bad Request", $buffer);
    }

    public function testRequestContentLengthBodyDataWillEmitDataEventOnRequestStream()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";
        $data .= "hello";

        $this->connection->emit('data', [$data]);
    }

    public function testRequestChunkedTransferEncodingRequestWillEmitDecodedDataEventOnRequestStream()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();
        $requestValidation = null;

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent, &$requestValidation) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
            $requestValidation = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', [$data]);

        $this->assertEquals('chunked', $requestValidation->getHeaderLine('Transfer-Encoding'));
    }

    public function testRequestChunkedTransferEncodingWithAdditionalDataWontBeEmitted()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";
        $data .= "0\r\n\r\n";
        $data .= "2\r\nhi\r\n";

        $this->connection->emit('data', [$data]);
    }

    public function testRequestChunkedTransferEncodingEmpty()
    {
        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', [$data]);
    }

    public function testRequestChunkedTransferEncodingHeaderCanBeUpperCase()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();
        $requestValidation = null;

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent, &$requestValidation) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
            $requestValidation = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: CHUNKED\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', [$data]);
        $this->assertEquals('CHUNKED', $requestValidation->getHeaderLine('Transfer-Encoding'));
    }

    public function testRequestChunkedTransferEncodingCanBeMixedUpperAndLowerCase()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: CHunKeD\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";
        $data .= "0\r\n\r\n";
        $this->connection->emit('data', [$data]);
    }

    public function testRequestContentLengthWillEmitDataEventAndEndEventAndAdditionalDataWillBeIgnored()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return resolve(new Response());
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";
        $data .= "hello";
        $data .= "world";

        $this->connection->emit('data', [$data]);
    }

    public function testRequestContentLengthWillEmitDataEventAndEndEventAndAdditionalDataWillBeIgnoredSplitted()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();


        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";
        $data .= "hello";

        $this->connection->emit('data', [$data]);

        $data = "world";

        $this->connection->emit('data', [$data]);
    }

    public function testRequestZeroContentLengthWillEmitEndEvent()
    {

        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 0\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', [$data]);
    }

    public function testRequestZeroContentLengthWillEmitEndAndAdditionalDataWillBeIgnored()
    {
        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 0\r\n";
        $data .= "\r\n";
        $data .= "hello";

        $this->connection->emit('data', [$data]);
    }

    public function testRequestZeroContentLengthWillEmitEndAndAdditionalDataWillBeIgnoredSplitted()
    {
        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 0\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', [$data]);

        $data = "hello";

        $this->connection->emit('data', [$data]);
    }

    public function testRequestInvalidChunkHeaderTooLongWillEmitErrorOnRequestStream()
    {
        $errorEvent = $this->expectCallableOnceWith($this->isInstanceOf(\Exception::class));
        $server = new StreamingServer(Loop::get(), function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
            return resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        for ($i = 0; $i < 1025; $i++) {
            $data .= 'a';
        }

        $this->connection->emit('data', [$data]);
    }

    public function testRequestInvalidChunkBodyTooLongWillEmitErrorOnRequestStream()
    {
        $errorEvent = $this->expectCallableOnceWith($this->isInstanceOf(\Exception::class));
        $server = new StreamingServer(Loop::get(), function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
        });

        $this->connection->expects($this->never())->method('close');

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello world\r\n";

        $this->connection->emit('data', [$data]);
    }

    public function testRequestUnexpectedEndOfRequestWithChunkedTransferConnectionWillEmitErrorOnRequestStream()
    {
        $errorEvent = $this->expectCallableOnceWith($this->isInstanceOf(\Exception::class));
        $server = new StreamingServer(Loop::get(), function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
        });

        $this->connection->expects($this->never())->method('close');

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";

        $this->connection->emit('data', [$data]);
        $this->connection->emit('end');
    }

    public function testRequestInvalidChunkHeaderWillEmitErrorOnRequestStream()
    {
        $errorEvent = $this->expectCallableOnceWith($this->isInstanceOf(\Exception::class));
        $server = new StreamingServer(Loop::get(), function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
        });

        $this->connection->expects($this->never())->method('close');

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "hello\r\nhello\r\n";

        $this->connection->emit('data', [$data]);
    }

    public function testRequestUnexpectedEndOfRequestWithContentLengthWillEmitErrorOnRequestStream()
    {
        $errorEvent = $this->expectCallableOnceWith($this->isInstanceOf(\Exception::class));
        $server = new StreamingServer(Loop::get(), function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
        });

        $this->connection->expects($this->never())->method('close');

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 500\r\n";
        $data .= "\r\n";
        $data .= "incomplete";

        $this->connection->emit('data', [$data]);
        $this->connection->emit('end');
    }

    public function testRequestWithoutBodyWillEmitEndOnRequestStream()
    {
        $dataEvent = $this->expectCallableNever();
        $closeEvent = $this->expectCallableOnce();
        $endEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new StreamingServer(Loop::get(), function ($request) use ($dataEvent, $closeEvent, $endEvent, $errorEvent){
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $this->connection->expects($this->never())->method('close');

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);
    }

    public function testRequestWithoutDefinedLengthWillIgnoreDataEvent()
    {
        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();
        $data .= "hello world";

        $this->connection->emit('data', [$data]);
    }

    public function testResponseWithBodyStreamWillUseChunkedTransferEncodingByDefault()
    {
        $stream = new ThroughStream();
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                [],
                $stream
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);
        $stream->emit('data', ['hello']);

        $this->assertStringContainsString("Transfer-Encoding: chunked", $buffer);
        $this->assertStringContainsString("hello", $buffer);
    }

    public function testResponseWithBodyStringWillOverwriteExplicitContentLengthAndTransferEncoding()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                200,
                [
                    'Content-Length' => 1000,
                    'Transfer-Encoding' => 'chunked'
                ],
                'hello'
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);

        $this->assertStringNotContainsString("Transfer-Encoding: chunked", $buffer);
        $this->assertStringContainsString("Content-Length: 5", $buffer);
        $this->assertStringContainsString("hello", $buffer);
    }

    public function testResponseContainsResponseBodyWithTransferEncodingChunkedForBodyWithUnknownSize()
    {
        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('getSize')->willReturn(null);
        $body->expects($this->once())->method('__toString')->willReturn('body');

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($body) {
            return new Response(
                200,
                [],
                $body
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("Transfer-Encoding: chunked", $buffer);
        $this->assertStringNotContainsString("Content-Length:", $buffer);
        $this->assertStringContainsString("body", $buffer);
    }

    public function testResponseContainsResponseBodyWithPlainBodyWithUnknownSizeForLegacyHttp10()
    {
        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('getSize')->willReturn(null);
        $body->expects($this->once())->method('__toString')->willReturn('body');

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($body) {
            return new Response(
                200,
                [],
                $body
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.0\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', [$data]);

        $this->assertStringNotContainsString("Transfer-Encoding: chunked", $buffer);
        $this->assertStringNotContainsString("Content-Length:", $buffer);
        $this->assertStringContainsString("body", $buffer);
    }

    public function testResponseWithCustomTransferEncodingWillBeIgnoredAndUseChunkedTransferEncodingInstead()
    {
        $stream = new ThroughStream();
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                [
                    'Transfer-Encoding' => 'custom'
                ],
                $stream
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);
        $stream->emit('data', ['hello']);

        $this->assertStringContainsString('Transfer-Encoding: chunked', $buffer);
        $this->assertStringNotContainsString('Transfer-Encoding: custom', $buffer);
        $this->assertStringContainsString("5\r\nhello\r\n", $buffer);
    }

    public function testResponseWithoutExplicitDateHeaderWillAddCurrentDateFromClock()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response();
        });

        $ref = new \ReflectionProperty($server, 'clock');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $clock = $ref->getValue($server);

        $ref = new \ReflectionProperty($clock, 'now');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($clock, 1652972091.3958);

        $buffer = '';
            $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertStringContainsString("Date: Thu, 19 May 2022 14:54:51 GMT\r\n", $buffer);
        $this->assertStringContainsString("\r\n\r\n", $buffer);
    }

    public function testResponseWithCustomDateHeaderOverwritesDefault()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                200,
                ["Date" => "Tue, 15 Nov 1994 08:12:31 GMT"]
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertStringContainsString("Date: Tue, 15 Nov 1994 08:12:31 GMT\r\n", $buffer);
        $this->assertStringContainsString("\r\n\r\n", $buffer);
    }

    public function testResponseWithEmptyDateHeaderRemovesDateHeader()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                200,
                ['Date' => '']
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertStringNotContainsString("Date:", $buffer);
        $this->assertStringContainsString("\r\n\r\n", $buffer);
    }

    public function testResponseCanContainMultipleCookieHeaders()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                200,
                [
                    'Set-Cookie' => [
                        'name=test',
                        'session=abc'
                    ],
                    'Date' => '',
                    'Server' => ''
                ]
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);

        $this->assertEquals("HTTP/1.1 200 OK\r\nSet-Cookie: name=test\r\nSet-Cookie: session=abc\r\nContent-Length: 0\r\nConnection: close\r\n\r\n", $buffer);
    }

    public function testReponseWithExpectContinueRequestContainsContinueWithLaterResponse()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response();
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Expect: 100-continue\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', [$data]);
        $this->assertStringContainsString("HTTP/1.1 100 Continue\r\n", $buffer);
        $this->assertStringContainsString("HTTP/1.1 200 OK\r\n", $buffer);
    }

    public function testResponseWithExpectContinueRequestWontSendContinueForHttp10()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response();
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.0\r\n";
        $data .= "Expect: 100-continue\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', [$data]);
        $this->assertStringContainsString("HTTP/1.0 200 OK\r\n", $buffer);
        $this->assertStringNotContainsString("HTTP/1.1 100 Continue\r\n\r\n", $buffer);
    }

    public function testInvalidCallbackFunctionLeadsToException()
    {
        $this->expectException(\InvalidArgumentException::class);
        new StreamingServer(Loop::get(), 'invalid');
    }

    public function testResponseBodyStreamWillStreamDataWithChunkedTransferEncoding()
    {
        $input = new ThroughStream();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($input) {
            return new Response(
                200,
                [],
                $input
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);
        $input->emit('data', ['1']);
        $input->emit('data', ['23']);

        $this->assertStringContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertStringContainsString("\r\n\r\n", $buffer);
        $this->assertStringContainsString("1\r\n1\r\n", $buffer);
        $this->assertStringContainsString("2\r\n23\r\n", $buffer);
    }

    public function testResponseBodyStreamWithContentLengthWillStreamTillLengthWithoutTransferEncoding()
    {
        $input = new ThroughStream();

        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($input) {
            return new Response(
                200,
                ['Content-Length' => 5],
                $input
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);
        $input->emit('data', ['hel']);
        $input->emit('data', ['lo']);

        $this->assertStringContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertStringContainsString("Content-Length: 5\r\n", $buffer);
        $this->assertStringNotContainsString("Transfer-Encoding", $buffer);
        $this->assertStringContainsString("\r\n\r\n", $buffer);
        $this->assertStringContainsString("hello", $buffer);
    }

    public function testResponseWithResponsePromise()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return resolve(new Response());
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);
        $this->assertStringContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertStringContainsString("\r\n\r\n", $buffer);
    }

    public function testResponseReturnInvalidTypeWillResultInError()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
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
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testResponseResolveWrongTypeInPromiseWillResultInError()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return resolve("invalid");
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
    }

    public function testResponseRejectedPromiseWillResultInErrorMessage()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Promise(function ($resolve, $reject) {
                $reject(new \Exception());
            });
        });
        $server->on('error', $this->expectCallableOnce());

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
    }

    public function testResponseExceptionInCallbackWillResultInErrorMessage()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Promise(function ($resolve, $reject) {
                throw new \Exception('Bad call');
            });
        });
        $server->on('error', $this->expectCallableOnce());

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
    }

    public function testResponseWithContentLengthHeaderForStringBodyOverwritesTransferEncoding()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response(
                200,
                ['Transfer-Encoding' => 'chunked'],
                'hello'
            );
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertStringContainsString("Content-Length: 5\r\n", $buffer);
        $this->assertStringContainsString("hello", $buffer);

        $this->assertStringNotContainsString("Transfer-Encoding", $buffer);
    }

    public function testResponseWillBeHandled()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
            return new Response();
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 200 OK\r\n", $buffer);
    }

    public function testResponseExceptionThrowInCallBackFunctionWillResultInErrorMessage()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
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
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertStringContainsString("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertEquals('hello', $exception->getPrevious()->getMessage());
    }

    public function testResponseThrowableThrowInCallBackFunctionWillResultInErrorMessage()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
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
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        try {
            $this->connection->emit('data', [$data]);
        } catch (\Error $e) {
            $this->markTestSkipped(
                'A \Throwable bubbled out of the request callback. ' .
                'This happened most probably due to react/promise:^1.0 being installed ' .
                'which does not support \Throwable.'
            );
        }

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertStringContainsString("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertEquals('hello', $exception->getPrevious()->getMessage());
    }

    public function testResponseRejectOfNonExceptionWillResultInErrorMessage()
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) {
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
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public static function provideInvalidResponse()
    {
        $response = new Response(200, [], '', '1.1', 'OK');

        yield [
            $response->withStatus(99, 'OK')
        ];
        yield [
            $response->withStatus(1000, 'OK')
        ];
        yield [
            $response->withStatus(200, "Invald\r\nReason: Yes")
        ];
        yield [
            $response->withHeader('Invalid', "Yes\r\n")
        ];
        yield [
            $response->withHeader('Invalid', "Yes\n")
        ];
        yield [
            $response->withHeader('Invalid', "Yes\r")
        ];
        yield [
            $response->withHeader("Inva\r\nlid", 'Yes')
        ];
        yield [
            $response->withHeader("Inva\nlid", 'Yes')
        ];
        yield [
            $response->withHeader("Inva\rlid", 'Yes')
        ];
        yield [
            $response->withHeader('Inva Lid', 'Yes')
        ];
        yield [
            $response->withHeader('Inva:Lid', 'Yes')
        ];
        yield [
            $response->withHeader('Invalid', "Val\0ue")
        ];
        yield [
            $response->withHeader("Inva\0lid", 'Yes')
        ];
    }

    /**
     * @dataProvider provideInvalidResponse
     * @param ResponseInterface $response
     */
    public function testInvalidResponseObjectWillResultInErrorMessage(ResponseInterface $response)
    {
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use ($response) {
            return $response;
        });

        $exception = null;
        $server->on('error', function (\Exception $ex) use (&$exception) {
            $exception = $ex;
        });

        $buffer = '';
        $this->connection
            ->expects($this->any())
            ->method('write')
            ->willReturnCallback(
                function ($data) use (&$buffer) {
                    $buffer .= $data;
                }
            );

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);

        $this->assertStringContainsString("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    public function testRequestServerRequestParams()
    {
        $requestValidation = null;
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
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
        $this->socket->emit('connection', [$this->connection]);

        $data = $this->createGetRequest();

        $this->connection->emit('data', [$data]);

        $serverParams = $requestValidation->getServerParams();

        $this->assertEquals('127.0.0.1', $serverParams['SERVER_ADDR']);
        $this->assertEquals('8080', $serverParams['SERVER_PORT']);
        $this->assertEquals('192.168.1.2', $serverParams['REMOTE_ADDR']);
        $this->assertEquals('80', $serverParams['REMOTE_PORT']);
        $this->assertNotNull($serverParams['REQUEST_TIME']);
        $this->assertNotNull($serverParams['REQUEST_TIME_FLOAT']);
    }

    public function testRequestQueryParametersWillBeAddedToRequest()
    {
        $requestValidation = null;
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET /foo.php?hello=world&test=bar HTTP/1.0\r\n\r\n";

        $this->connection->emit('data', [$data]);

        $queryParams = $requestValidation->getQueryParams();

        $this->assertEquals('world', $queryParams['hello']);
        $this->assertEquals('bar', $queryParams['test']);
    }

    public function testRequestCookieWillBeAddedToServerRequest()
    {
        $requestValidation = null;
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Cookie: hello=world\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', [$data]);

        $this->assertEquals(['hello' => 'world'], $requestValidation->getCookieParams());
    }

    public function testRequestInvalidMultipleCookiesWontBeAddedToServerRequest()
    {
        $requestValidation = null;
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Cookie: hello=world\r\n";
        $data .= "Cookie: test=failed\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', [$data]);
        $this->assertEquals([], $requestValidation->getCookieParams());
    }

    public function testRequestCookieWithSeparatorWillBeAddedToServerRequest()
    {
        $requestValidation = null;
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Cookie: hello=world; test=abc\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', [$data]);
        $this->assertEquals(['hello' => 'world', 'test' => 'abc'], $requestValidation->getCookieParams());
    }

    public function testRequestCookieWithCommaValueWillBeAddedToServerRequest()
    {
        $requestValidation = null;
        $server = new StreamingServer(Loop::get(), function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Cookie: test=abc,def; hello=world\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', [$data]);
        $this->assertEquals(['test' => 'abc,def', 'hello' => 'world'], $requestValidation->getCookieParams());
    }

    public function testNewConnectionWillInvokeParserOnce()
    {
        $server = new StreamingServer(Loop::get(), $this->expectCallableNever());

        $parser = $this->createMock(RequestHeaderParser::class);
        $parser->expects($this->once())->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);
    }

    public function testNewConnectionWillInvokeParserOnceAndInvokeRequestHandlerWhenParserIsDoneForHttp10()
    {
        $request = new ServerRequest('GET', 'http://localhost/', [], '', '1.0');

        $server = new StreamingServer(Loop::get(), $this->expectCallableOnceWith($request));

        $parser = $this->createMock(RequestHeaderParser::class);
        $parser->expects($this->once())->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $this->connection->expects($this->once())->method('write');
        $this->connection->expects($this->once())->method('end');

        // pretend parser just finished parsing
        $server->handleRequest($this->connection, $request);
    }

    public function testNewConnectionWillInvokeParserOnceAndInvokeRequestHandlerWhenParserIsDoneForHttp11ConnectionClose()
    {
        $request = new ServerRequest('GET', 'http://localhost/', ['Connection' => 'close']);

        $server = new StreamingServer(Loop::get(), $this->expectCallableOnceWith($request));

        $parser = $this->createMock(RequestHeaderParser::class);
        $parser->expects($this->once())->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $this->connection->expects($this->once())->method('write');
        $this->connection->expects($this->once())->method('end');

        // pretend parser just finished parsing
        $server->handleRequest($this->connection, $request);
    }

    public function testNewConnectionWillInvokeParserOnceAndInvokeRequestHandlerWhenParserIsDoneAndRequestHandlerReturnsConnectionClose()
    {
        $request = new ServerRequest('GET', 'http://localhost/');

        $server = new StreamingServer(Loop::get(), function () {
            return new Response(200, ['Connection' => 'close']);
        });

        $parser = $this->createMock(RequestHeaderParser::class);
        $parser->expects($this->once())->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $this->connection->expects($this->once())->method('write');
        $this->connection->expects($this->once())->method('end');

        // pretend parser just finished parsing
        $server->handleRequest($this->connection, $request);
    }

    public function testNewConnectionWillInvokeParserTwiceAfterInvokingRequestHandlerWhenConnectionCanBeKeptAliveForHttp11Default()
    {
        $request = new ServerRequest('GET', 'http://localhost/');

        $server = new StreamingServer(Loop::get(), function () {
            return new Response();
        });

        $parser = $this->createMock(RequestHeaderParser::class);
        $parser->expects($this->exactly(2))->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $this->connection->expects($this->once())->method('write');
        $this->connection->expects($this->never())->method('end');

        // pretend parser just finished parsing
        $server->handleRequest($this->connection, $request);
    }

    public function testNewConnectionWillInvokeParserTwiceAfterInvokingRequestHandlerWhenConnectionCanBeKeptAliveForHttp10ConnectionKeepAlive()
    {
        $request = new ServerRequest('GET', 'http://localhost/', ['Connection' => 'keep-alive'], '', '1.0');

        $server = new StreamingServer(Loop::get(), function () {
            return new Response();
        });

        $parser = $this->createMock(RequestHeaderParser::class);
        $parser->expects($this->exactly(2))->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $this->connection->expects($this->once())->method('write');
        $this->connection->expects($this->never())->method('end');

        // pretend parser just finished parsing
        $server->handleRequest($this->connection, $request);
    }

    public function testNewConnectionWillInvokeParserOnceAfterInvokingRequestHandlerWhenStreamingResponseBodyKeepsStreaming()
    {
        $request = new ServerRequest('GET', 'http://localhost/');

        $body = new ThroughStream();
        $server = new StreamingServer(Loop::get(), function () use ($body) {
            return new Response(200, [], $body);
        });

        $parser = $this->createMock(RequestHeaderParser::class);
        $parser->expects($this->once())->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $this->connection->expects($this->once())->method('write');
        $this->connection->expects($this->never())->method('end');

        // pretend parser just finished parsing
        $server->handleRequest($this->connection, $request);
    }

    public function testNewConnectionWillInvokeParserTwiceAfterInvokingRequestHandlerWhenStreamingResponseBodyEnds()
    {
        $request = new ServerRequest('GET', 'http://localhost/');

        $body = new ThroughStream();
        $server = new StreamingServer(Loop::get(), function () use ($body) {
            return new Response(200, [], $body);
        });

        $parser = $this->createMock(RequestHeaderParser::class);
        $parser->expects($this->exactly(2))->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', [$this->connection]);

        $this->connection->expects($this->exactly(2))->method('write');
        $this->connection->expects($this->never())->method('end');

        // pretend parser just finished parsing
        $server->handleRequest($this->connection, $request);

        $this->assertCount(2, $this->connection->listeners('close'));
        $body->end();
        $this->assertCount(1, $this->connection->listeners('close'));
    }

    public function testCompletingARequestWillRemoveConnectionOnCloseListener()
    {
        $connection = $this->mockConnection(['removeListener']);

        $request = new ServerRequest('GET', 'http://localhost/');

        $server = new StreamingServer(Loop::get(), function () {
            return resolve(new Response());
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', [$connection]);

        $connection->expects($this->once())->method('removeListener');

        // pretend parser just finished parsing
        $server->handleRequest($connection, $request);
    }

    private function createGetRequest()
    {
        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "\r\n";

        return $data;
    }
}
