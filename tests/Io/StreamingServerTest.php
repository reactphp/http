<?php

namespace React\Tests\Http\Io;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\Io\StreamingServer;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\Promise;
use React\Stream\ThroughStream;
use React\Tests\Http\SocketServerStub;
use React\Tests\Http\TestCase;

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
        $server = $this->createStreamingServer($this->expectCallableNever());

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = '';
        $data .= "GET / HTTP/1.1\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testRequestEventIsEmitted()
    {
        $server = $this->createStreamingServer($this->expectCallableOnce());

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    /**
     * @requires PHP 5.4
     */
    public function testRequestEventIsEmittedForArrayCallable()
    {
        $this->called = null;
        $server = $this->createStreamingServer(array($this, 'helperCallableOnce'));

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));

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
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$i, &$requestAssertion) {
            $i++;
            $requestAssertion = $request;
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

    public function testRequestEventWithSingleRequestHandlerArray()
    {
        $i = 0;
        $requestAssertion = null;
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$i, &$requestAssertion) {
            $i++;
            $requestAssertion = $request;
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
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
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
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
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
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
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
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
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
        $server = $this->createStreamingServer('var_dump');
        $server->on('error', $this->expectCallableOnce());

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testRequestOptionsAsterisk()
    {
        $requestAssertion = null;
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
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
        $server = $this->createStreamingServer($this->expectCallableNever());
        $server->on('error', $this->expectCallableOnce());

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET * HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testRequestConnectAuthorityForm()
    {
        $requestAssertion = null;
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
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

    public function testRequestConnectWithoutHostWillBePassesAsIs()
    {
        $requestAssertion = null;
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
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
        $this->assertFalse($requestAssertion->hasHeader('Host'));
    }

    public function testRequestConnectAuthorityFormWithDefaultPortWillBePassedAsIs()
    {
        $requestAssertion = null;
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
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
        $this->assertNull($requestAssertion->getUri()->getPort());
        $this->assertSame('example.com:80', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestConnectAuthorityFormNonMatchingHostWillBePassedAsIs()
    {
        $requestAssertion = null;
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
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
        $this->assertNull($requestAssertion->getUri()->getPort());
        $this->assertSame('other.example.org', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestConnectOriginFormRequestTargetWillReject()
    {
        $server = $this->createStreamingServer($this->expectCallableNever());
        $server->on('error', $this->expectCallableOnce());

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "CONNECT / HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testRequestNonConnectWithAuthorityRequestTargetWillReject()
    {
        $server = $this->createStreamingServer($this->expectCallableNever());
        $server->on('error', $this->expectCallableOnce());

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET example.com:80 HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testRequestWithoutHostEventUsesSocketAddress()
    {
        $requestAssertion = null;

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
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

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
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

    public function testRequestAbsoluteNonMatchingHostWillBePassedAsIs()
    {
        $requestAssertion = null;

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
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
        $this->assertSame('other.example.org', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestAbsoluteWithoutHostWillReject()
    {
        $server = $this->createStreamingServer($this->expectCallableNever());
        $server->on('error', $this->expectCallableOnce());

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET http://example.com:8080/test HTTP/1.1\r\n\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testRequestOptionsAsteriskEvent()
    {
        $requestAssertion = null;

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
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

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
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

    public function testRequestPauseWillBeForwardedToConnection()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            $request->getBody()->pause();
        });

        $this->connection->expects($this->once())->method('pause');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testRequestResumeWillBeForwardedToConnection()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            $request->getBody()->resume();
        });

        $this->connection->expects($this->once())->method('resume');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testRequestCloseWillNotCloseConnection()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            $request->getBody()->close();
        });

        $this->connection->expects($this->never())->method('close');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestPauseAfterCloseWillNotBeForwarded()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            $request->getBody()->close();
            $request->getBody()->pause();
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->never())->method('pause');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestResumeAfterCloseWillNotBeForwarded()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            $request->getBody()->close();
            $request->getBody()->resume();
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->never())->method('resume');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestEventWithoutBodyWillNotEmitData()
    {
        $never = $this->expectCallableNever();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($never) {
            $request->getBody()->on('data', $never);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestEventWithSecondDataEventWillEmitBodyData()
    {
        $once = $this->expectCallableOnceWith('incomplete');

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($once) {
            $request->getBody()->on('data', $once);
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

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($once) {
            $request->getBody()->on('data', $once);
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

    public function testResponseContainsServerHeader()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
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

        $this->assertContainsString("\r\nServer: ReactPHP/1\r\n", $buffer);
    }

    public function testResponsePendingPromiseWillNotSendAnything()
    {
        $never = $this->expectCallableNever();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($never) {
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

    public function testResponsePendingPromiseWillBeCancelledIfConnectionCloses()
    {
        $once = $this->expectCallableOnce();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($once) {
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

    public function testResponseBodyStreamAlreadyClosedWillSendEmptyBodyChunkedEncoded()
    {
        $stream = new ThroughStream();
        $stream->close();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                array(),
                $stream
            );
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

    public function testResponseBodyStreamEndingWillSendEmptyBodyChunkedEncoded()
    {
        $stream = new ThroughStream();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                array(),
                $stream
            );
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

    public function testResponseBodyStreamAlreadyClosedWillSendEmptyBodyPlainHttp10()
    {
        $stream = new ThroughStream();
        $stream->close();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                array(),
                $stream
            );
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

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                array(),
                $stream
            );
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

    public function testResponseBodyStreamWillBeClosedIfConnectionEmitsCloseEvent()
    {
        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                array(),
                $stream
            );
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
        $this->connection->emit('close');
    }

    public function testResponseUpgradeInResponseCanBeUsedToAdvertisePossibleUpgrade()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                200,
                array(
                    'date' => '',
                    'server' => '',
                    'Upgrade' => 'demo'
                ),
                'foo'
            );
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

        $this->assertEquals("HTTP/1.1 200 OK\r\nUpgrade: demo\r\nContent-Length: 3\r\n\r\nfoo", $buffer);
    }

    public function testResponseUpgradeWishInRequestCanBeIgnoredByReturningNormalResponse()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                200,
                array(
                    'date' => '',
                    'server' => ''
                ),
                'foo'
            );
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

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\nUpgrade: demo\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertEquals("HTTP/1.1 200 OK\r\nContent-Length: 3\r\n\r\nfoo", $buffer);
    }

    public function testResponseUpgradeSwitchingProtocolIncludesConnectionUpgradeHeaderWithoutContentLength()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                101,
                array(
                    'date' => '',
                    'server' => '',
                    'Upgrade' => 'demo'
                ),
                'foo'
            );
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

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\nUpgrade: demo\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertEquals("HTTP/1.1 101 Switching Protocols\r\nUpgrade: demo\r\nConnection: upgrade\r\n\r\nfoo", $buffer);
    }

    public function testResponseUpgradeSwitchingProtocolWithStreamWillPipeDataToConnection()
    {
        $stream = new ThroughStream();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                101,
                array(
                    'date' => '',
                    'server' => '',
                    'Upgrade' => 'demo'
                ),
                $stream
            );
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

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\nUpgrade: demo\r\n\r\n";
        $this->connection->emit('data', array($data));

        $stream->write('hello');
        $stream->write('world');

        $this->assertEquals("HTTP/1.1 101 Switching Protocols\r\nUpgrade: demo\r\nConnection: upgrade\r\n\r\nhelloworld", $buffer);
    }

    public function testResponseConnectMethodStreamWillPipeDataToConnection()
    {
        $stream = new ThroughStream();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                array(),
                $stream
            );
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


    public function testResponseConnectMethodStreamWillPipeDataFromConnection()
    {
        $stream = new ThroughStream();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                array(),
                $stream
            );
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('pipe')->with($stream);

        $data = "CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\n\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testResponseContainsSameRequestProtocolVersionAndChunkedBodyForHttp11()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                200,
                array(),
                'bye'
            );
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

        $this->assertContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContainsString("bye", $buffer);
    }

    public function testResponseContainsSameRequestProtocolVersionAndRawBodyForHttp10()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                200,
                array(),
                'bye'
            );
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

        $this->assertContainsString("HTTP/1.0 200 OK\r\n", $buffer);
        $this->assertContainsString("\r\n\r\n", $buffer);
        $this->assertContainsString("bye", $buffer);
    }

    public function testResponseContainsNoResponseBodyForHeadRequest()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                200,
                array(),
                'bye'
            );
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

        $this->assertContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContainsString("\r\nContent-Length: 3\r\n", $buffer);
        $this->assertNotContainsString("bye", $buffer);
    }

    public function testResponseContainsNoResponseBodyForHeadRequestWithStreamingResponse()
    {
        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                array('Content-Length' => '3'),
                $stream
            );
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

        $this->assertContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContainsString("\r\nContent-Length: 3\r\n", $buffer);
    }

    public function testResponseContainsNoResponseBodyAndNoContentLengthForNoContentStatus()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                204,
                array(),
                'bye'
            );
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

        $this->assertContainsString("HTTP/1.1 204 No Content\r\n", $buffer);
        $this->assertNotContainsString("\r\nContent-Length: 3\r\n", $buffer);
        $this->assertNotContainsString("bye", $buffer);
    }

    public function testResponseContainsNoResponseBodyAndNoContentLengthForNoContentStatusResponseWithStreamingBody()
    {
        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                204,
                array('Content-Length' => '3'),
                $stream
            );
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

        $this->assertContainsString("HTTP/1.1 204 No Content\r\n", $buffer);
        $this->assertNotContainsString("\r\nContent-Length: 3\r\n", $buffer);
    }

    public function testResponseContainsNoContentLengthHeaderForNotModifiedStatus()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                304,
                array(),
                ''
            );
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

        $this->assertContainsString("HTTP/1.1 304 Not Modified\r\n", $buffer);
        $this->assertNotContainsString("\r\nContent-Length: 0\r\n", $buffer);
    }

    public function testResponseContainsExplicitContentLengthHeaderForNotModifiedStatus()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                304,
                array('Content-Length' => 3),
                ''
            );
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

        $this->assertContainsString("HTTP/1.1 304 Not Modified\r\n", $buffer);
        $this->assertContainsString("\r\nContent-Length: 3\r\n", $buffer);
    }

    public function testResponseContainsExplicitContentLengthHeaderForHeadRequests()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                200,
                array('Content-Length' => 3),
                ''
            );
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

        $this->assertContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContainsString("\r\nContent-Length: 3\r\n", $buffer);
    }

    public function testResponseContainsNoResponseBodyForNotModifiedStatus()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                304,
                array(),
                'bye'
            );
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

        $this->assertContainsString("HTTP/1.1 304 Not Modified\r\n", $buffer);
        $this->assertContainsString("\r\nContent-Length: 3\r\n", $buffer);
        $this->assertNotContainsString("bye", $buffer);
    }

    public function testResponseContainsNoResponseBodyForNotModifiedStatusWithStreamingBody()
    {
        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                304,
                array('Content-Length' => '3'),
                $stream
            );
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

        $this->assertContainsString("HTTP/1.1 304 Not Modified\r\n", $buffer);
        $this->assertContainsString("\r\nContent-Length: 3\r\n", $buffer);
    }

    public function testRequestInvalidHttpProtocolVersionWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = $this->createStreamingServer($this->expectCallableNever());
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

        $this->assertContainsString("HTTP/1.1 505 HTTP Version not supported\r\n", $buffer);
        $this->assertContainsString("\r\n\r\n", $buffer);
        $this->assertContainsString("Error 505: HTTP Version not supported", $buffer);
    }

    public function testRequestOverflowWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = $this->createStreamingServer($this->expectCallableNever());
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
        $data .= str_repeat('A', 8193 - strlen($data)) . "\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('OverflowException', $error);

        $this->assertContainsString("HTTP/1.1 431 Request Header Fields Too Large\r\n", $buffer);
        $this->assertContainsString("\r\n\r\nError 431: Request Header Fields Too Large", $buffer);
    }

    public function testRequestInvalidWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = $this->createStreamingServer($this->expectCallableNever());
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

        $this->assertContainsString("HTTP/1.1 400 Bad Request\r\n", $buffer);
        $this->assertContainsString("\r\n\r\nError 400: Bad Request", $buffer);
    }

    public function testRequestContentLengthBodyDataWillEmitDataEventOnRequestStream()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";
        $data .= "hello";

        $this->connection->emit('data', array($data));
    }

    public function testRequestChunkedTransferEncodingRequestWillEmitDecodedDataEventOnRequestStream()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();
        $requestValidation = null;

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent, &$requestValidation) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
            $requestValidation = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', array($data));

        $this->assertEquals('chunked', $requestValidation->getHeaderLine('Transfer-Encoding'));
    }

    public function testRequestChunkedTransferEncodingWithAdditionalDataWontBeEmitted()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";
        $data .= "0\r\n\r\n";
        $data .= "2\r\nhi\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testRequestChunkedTransferEncodingEmpty()
    {
        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testRequestChunkedTransferEncodingHeaderCanBeUpperCase()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();
        $requestValidation = null;

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent, &$requestValidation) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
            $requestValidation = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: CHUNKED\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";
        $data .= "0\r\n\r\n";

        $this->connection->emit('data', array($data));
        $this->assertEquals('CHUNKED', $requestValidation->getHeaderLine('Transfer-Encoding'));
    }

    public function testRequestChunkedTransferEncodingCanBeMixedUpperAndLowerCase()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: CHunKeD\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";
        $data .= "0\r\n\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testRequestContentLengthWillEmitDataEventAndEndEventAndAdditionalDataWillBeIgnored()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";
        $data .= "hello";
        $data .= "world";

        $this->connection->emit('data', array($data));
    }

    public function testRequestContentLengthWillEmitDataEventAndEndEventAndAdditionalDataWillBeIgnoredSplitted()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();


        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";
        $data .= "hello";

        $this->connection->emit('data', array($data));

        $data = "world";

        $this->connection->emit('data', array($data));
    }

    public function testRequestZeroContentLengthWillEmitEndEvent()
    {

        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 0\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testRequestZeroContentLengthWillEmitEndAndAdditionalDataWillBeIgnored()
    {
        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 0\r\n";
        $data .= "\r\n";
        $data .= "hello";

        $this->connection->emit('data', array($data));
    }

    public function testRequestZeroContentLengthWillEmitEndAndAdditionalDataWillBeIgnoredSplitted()
    {
        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 0\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));

        $data = "hello";

        $this->connection->emit('data', array($data));
    }

    public function testRequestInvalidChunkHeaderTooLongWillEmitErrorOnRequestStream()
    {
        $errorEvent = $this->expectCallableOnceWith($this->isInstanceOf('Exception'));
        $server = $this->createStreamingServer(function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        for ($i = 0; $i < 1025; $i++) {
            $data .= 'a';
        }

        $this->connection->emit('data', array($data));
    }

    public function testRequestInvalidChunkBodyTooLongWillEmitErrorOnRequestStream()
    {
        $errorEvent = $this->expectCallableOnceWith($this->isInstanceOf('Exception'));
        $server = $this->createStreamingServer(function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
        });

        $this->connection->expects($this->never())->method('close');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello world\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testRequestUnexpectedEndOfRequestWithChunkedTransferConnectionWillEmitErrorOnRequestStream()
    {
        $errorEvent = $this->expectCallableOnceWith($this->isInstanceOf('Exception'));
        $server = $this->createStreamingServer(function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
        });

        $this->connection->expects($this->never())->method('close');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "5\r\nhello\r\n";

        $this->connection->emit('data', array($data));
        $this->connection->emit('end');
    }

    public function testRequestInvalidChunkHeaderWillEmitErrorOnRequestStream()
    {
        $errorEvent = $this->expectCallableOnceWith($this->isInstanceOf('Exception'));
        $server = $this->createStreamingServer(function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
        });

        $this->connection->expects($this->never())->method('close');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Transfer-Encoding: chunked\r\n";
        $data .= "\r\n";
        $data .= "hello\r\nhello\r\n";

        $this->connection->emit('data', array($data));
    }

    public function testRequestUnexpectedEndOfRequestWithContentLengthWillEmitErrorOnRequestStream()
    {
        $errorEvent = $this->expectCallableOnceWith($this->isInstanceOf('Exception'));
        $server = $this->createStreamingServer(function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
        });

        $this->connection->expects($this->never())->method('close');

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 500\r\n";
        $data .= "\r\n";
        $data .= "incomplete";

        $this->connection->emit('data', array($data));
        $this->connection->emit('end');
    }

    public function testRequestWithoutBodyWillEmitEndOnRequestStream()
    {
        $dataEvent = $this->expectCallableNever();
        $closeEvent = $this->expectCallableOnce();
        $endEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = $this->createStreamingServer(function ($request) use ($dataEvent, $closeEvent, $endEvent, $errorEvent){
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('error', $errorEvent);
        });

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

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $data .= "hello world";

        $this->connection->emit('data', array($data));
    }

    public function testResponseWithBodyStreamWillUseChunkedTransferEncodingByDefault()
    {
        $stream = new ThroughStream();
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                array(),
                $stream
            );
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

        $this->assertContainsString("Transfer-Encoding: chunked", $buffer);
        $this->assertContainsString("hello", $buffer);
    }

    public function testResponseWithBodyStringWillOverwriteExplicitContentLengthAndTransferEncoding()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                200,
                array(
                    'Content-Length' => 1000,
                    'Transfer-Encoding' => 'chunked'
                ),
                'hello'
            );
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

        $this->assertNotContainsString("Transfer-Encoding: chunked", $buffer);
        $this->assertContainsString("Content-Length: 5", $buffer);
        $this->assertContainsString("hello", $buffer);
    }

    public function testResponseContainsResponseBodyWithTransferEncodingChunkedForBodyWithUnknownSize()
    {
        $body = $this->getMockBuilder('Psr\Http\Message\StreamInterface')->getMock();
        $body->expects($this->once())->method('getSize')->willReturn(null);
        $body->expects($this->once())->method('__toString')->willReturn('body');

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($body) {
            return new Response(
                200,
                array(),
                $body
            );
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

        $this->assertContainsString("Transfer-Encoding: chunked", $buffer);
        $this->assertNotContainsString("Content-Length:", $buffer);
        $this->assertContainsString("body", $buffer);
    }

    public function testResponseContainsResponseBodyWithPlainBodyWithUnknownSizeForLegacyHttp10()
    {
        $body = $this->getMockBuilder('Psr\Http\Message\StreamInterface')->getMock();
        $body->expects($this->once())->method('getSize')->willReturn(null);
        $body->expects($this->once())->method('__toString')->willReturn('body');

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($body) {
            return new Response(
                200,
                array(),
                $body
            );
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

        $this->assertNotContainsString("Transfer-Encoding: chunked", $buffer);
        $this->assertNotContainsString("Content-Length:", $buffer);
        $this->assertContainsString("body", $buffer);
    }

    public function testResponseWithCustomTransferEncodingWillBeIgnoredAndUseChunkedTransferEncodingInstead()
    {
        $stream = new ThroughStream();
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($stream) {
            return new Response(
                200,
                array(
                    'Transfer-Encoding' => 'custom'
                ),
                $stream
            );
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

        $this->assertContainsString('Transfer-Encoding: chunked', $buffer);
        $this->assertNotContainsString('Transfer-Encoding: custom', $buffer);
        $this->assertContainsString("5\r\nhello\r\n", $buffer);
    }

    public function testResponseWithoutExplicitDateHeaderWillAddCurrentDateFromClock()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response();
        });

        $ref = new \ReflectionProperty($server, 'clock');
        $ref->setAccessible(true);
        $clock = $ref->getValue($server);

        $ref = new \ReflectionProperty($clock, 'now');
        $ref->setAccessible(true);
        $ref->setValue($clock, 1652972091.3958);

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

        $this->assertContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContainsString("Date: Thu, 19 May 2022 14:54:51 GMT\r\n", $buffer);
        $this->assertContainsString("\r\n\r\n", $buffer);
    }

    public function testResponseWithCustomDateHeaderOverwritesDefault()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                200,
                array("Date" => "Tue, 15 Nov 1994 08:12:31 GMT")
            );
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

        $this->assertContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContainsString("Date: Tue, 15 Nov 1994 08:12:31 GMT\r\n", $buffer);
        $this->assertContainsString("\r\n\r\n", $buffer);
    }

    public function testResponseWithEmptyDateHeaderRemovesDateHeader()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                200,
                array('Date' => '')
            );
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

        $this->assertContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertNotContainsString("Date:", $buffer);
        $this->assertContainsString("\r\n\r\n", $buffer);
    }

    public function testResponseCanContainMultipleCookieHeaders()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                200,
                array(
                    'Set-Cookie' => array(
                        'name=test',
                        'session=abc'
                    ),
                    'Date' => '',
                    'Server' => ''
                )
            );
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

        $this->assertEquals("HTTP/1.1 200 OK\r\nSet-Cookie: name=test\r\nSet-Cookie: session=abc\r\nContent-Length: 0\r\nConnection: close\r\n\r\n", $buffer);
    }

    public function testReponseWithExpectContinueRequestContainsContinueWithLaterResponse()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
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

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Expect: 100-continue\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));
        $this->assertContainsString("HTTP/1.1 100 Continue\r\n", $buffer);
        $this->assertContainsString("HTTP/1.1 200 OK\r\n", $buffer);
    }

    public function testResponseWithExpectContinueRequestWontSendContinueForHttp10()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
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

        $data = "GET / HTTP/1.0\r\n";
        $data .= "Expect: 100-continue\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));
        $this->assertContainsString("HTTP/1.0 200 OK\r\n", $buffer);
        $this->assertNotContainsString("HTTP/1.1 100 Continue\r\n\r\n", $buffer);
    }

    public function testInvalidCallbackFunctionLeadsToException()
    {
        $this->setExpectedException('InvalidArgumentException');
        $server = $this->createStreamingServer('invalid');
    }

    public function testResponseBodyStreamWillStreamDataWithChunkedTransferEncoding()
    {
        $input = new ThroughStream();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($input) {
            return new Response(
                200,
                array(),
                $input
            );
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

        $this->assertContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContainsString("\r\n\r\n", $buffer);
        $this->assertContainsString("1\r\n1\r\n", $buffer);
        $this->assertContainsString("2\r\n23\r\n", $buffer);
    }

    public function testResponseBodyStreamWithContentLengthWillStreamTillLengthWithoutTransferEncoding()
    {
        $input = new ThroughStream();

        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use ($input) {
            return new Response(
                200,
                array('Content-Length' => 5),
                $input
            );
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

        $this->assertContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContainsString("Content-Length: 5\r\n", $buffer);
        $this->assertNotContainsString("Transfer-Encoding", $buffer);
        $this->assertContainsString("\r\n\r\n", $buffer);
        $this->assertContainsString("hello", $buffer);
    }

    public function testResponseWithResponsePromise()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
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
        $this->assertContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContainsString("\r\n\r\n", $buffer);
    }

    public function testResponseReturnInvalidTypeWillResultInError()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
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

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContainsString("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertInstanceOf('RuntimeException', $exception);
    }

    public function testResponseResolveWrongTypeInPromiseWillResultInError()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
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

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContainsString("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
    }

    public function testResponseRejectedPromiseWillResultInErrorMessage()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
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

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContainsString("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
    }

    public function testResponseExceptionInCallbackWillResultInErrorMessage()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
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

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContainsString("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
    }

    public function testResponseWithContentLengthHeaderForStringBodyOverwritesTransferEncoding()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
            return new Response(
                200,
                array('Transfer-Encoding' => 'chunked'),
                'hello'
            );
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

        $this->assertContainsString("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContainsString("Content-Length: 5\r\n", $buffer);
        $this->assertContainsString("hello", $buffer);

        $this->assertNotContainsString("Transfer-Encoding", $buffer);
    }

    public function testResponseWillBeHandled()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
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

        $this->assertContainsString("HTTP/1.1 200 OK\r\n", $buffer);
    }

    public function testResponseExceptionThrowInCallBackFunctionWillResultInErrorMessage()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
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

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RuntimeException', $exception);
        $this->assertContainsString("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertEquals('hello', $exception->getPrevious()->getMessage());
    }

    /**
     * @requires PHP 7
     */
    public function testResponseThrowableThrowInCallBackFunctionWillResultInErrorMessage()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
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
        $this->assertContainsString("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertEquals('hello', $exception->getPrevious()->getMessage());
    }

    public function testResponseRejectOfNonExceptionWillResultInErrorMessage()
    {
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) {
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

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContainsString("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertInstanceOf('RuntimeException', $exception);
    }

    public function testRequestServerRequestParams()
    {
        $requestValidation = null;
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestValidation) {
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

    public function testRequestQueryParametersWillBeAddedToRequest()
    {
        $requestValidation = null;
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET /foo.php?hello=world&test=bar HTTP/1.0\r\n\r\n";

        $this->connection->emit('data', array($data));

        $queryParams = $requestValidation->getQueryParams();

        $this->assertEquals('world', $queryParams['hello']);
        $this->assertEquals('bar', $queryParams['test']);
    }

    public function testRequestCookieWillBeAddedToServerRequest()
    {
        $requestValidation = null;
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Cookie: hello=world\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));

        $this->assertEquals(array('hello' => 'world'), $requestValidation->getCookieParams());
    }

    public function testRequestInvalidMultipleCookiesWontBeAddedToServerRequest()
    {
        $requestValidation = null;
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Cookie: hello=world\r\n";
        $data .= "Cookie: test=failed\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));
        $this->assertEquals(array(), $requestValidation->getCookieParams());
    }

    public function testRequestCookieWithSeparatorWillBeAddedToServerRequest()
    {
        $requestValidation = null;
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Cookie: hello=world; test=abc\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));
        $this->assertEquals(array('hello' => 'world', 'test' => 'abc'), $requestValidation->getCookieParams());
    }

    public function testRequestCookieWithCommaValueWillBeAddedToServerRequest()
    {
        $requestValidation = null;
        $server = $this->createStreamingServer(function (ServerRequestInterface $request) use (&$requestValidation) {
            $requestValidation = $request;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Cookie: test=abc,def; hello=world\r\n";
        $data .= "\r\n";

        $this->connection->emit('data', array($data));
        $this->assertEquals(array('test' => 'abc,def', 'hello' => 'world'), $requestValidation->getCookieParams());
    }

    public function testNewConnectionWillInvokeParserOnce()
    {
        $server = $this->createStreamingServer($this->expectCallableNever());

        $parser = $this->getMockBuilder('React\Http\Io\RequestHeaderParser')->disableOriginalConstructor()->getMock();
        $parser->expects($this->once())->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        $ref->setAccessible(true);
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));
    }

    public function testNewConnectionWillInvokeParserOnceAndInvokeRequestHandlerWhenParserIsDoneForHttp10()
    {
        $request = new ServerRequest('GET', 'http://localhost/', array(), '', '1.0');

        $server = $this->createStreamingServer($this->expectCallableOnceWith($request));

        $parser = $this->getMockBuilder('React\Http\Io\RequestHeaderParser')->disableOriginalConstructor()->getMock();
        $parser->expects($this->once())->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        $ref->setAccessible(true);
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write');
        $this->connection->expects($this->once())->method('end');

        // pretend parser just finished parsing
        $server->handleRequest($this->connection, $request);
    }

    public function testNewConnectionWillInvokeParserOnceAndInvokeRequestHandlerWhenParserIsDoneForHttp11ConnectionClose()
    {
        $request = new ServerRequest('GET', 'http://localhost/', array('Connection' => 'close'));

        $server = $this->createStreamingServer($this->expectCallableOnceWith($request));

        $parser = $this->getMockBuilder('React\Http\Io\RequestHeaderParser')->disableOriginalConstructor()->getMock();
        $parser->expects($this->once())->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        $ref->setAccessible(true);
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write');
        $this->connection->expects($this->once())->method('end');

        // pretend parser just finished parsing
        $server->handleRequest($this->connection, $request);
    }

    public function testNewConnectionWillInvokeParserOnceAndInvokeRequestHandlerWhenParserIsDoneAndRequestHandlerReturnsConnectionClose()
    {
        $request = new ServerRequest('GET', 'http://localhost/');

        $server = $this->createStreamingServer(function () {
            return new Response(200, array('Connection' => 'close'));
        });

        $parser = $this->getMockBuilder('React\Http\Io\RequestHeaderParser')->disableOriginalConstructor()->getMock();
        $parser->expects($this->once())->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        $ref->setAccessible(true);
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write');
        $this->connection->expects($this->once())->method('end');

        // pretend parser just finished parsing
        $server->handleRequest($this->connection, $request);
    }

    public function testNewConnectionWillInvokeParserTwiceAfterInvokingRequestHandlerWhenConnectionCanBeKeptAliveForHttp11Default()
    {
        $request = new ServerRequest('GET', 'http://localhost/');

        $server = $this->createStreamingServer(function () {
            return new Response();
        });

        $parser = $this->getMockBuilder('React\Http\Io\RequestHeaderParser')->disableOriginalConstructor()->getMock();
        $parser->expects($this->exactly(2))->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        $ref->setAccessible(true);
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write');
        $this->connection->expects($this->never())->method('end');

        // pretend parser just finished parsing
        $server->handleRequest($this->connection, $request);
    }

    public function testNewConnectionWillInvokeParserTwiceAfterInvokingRequestHandlerWhenConnectionCanBeKeptAliveForHttp10ConnectionKeepAlive()
    {
        $request = new ServerRequest('GET', 'http://localhost/', array('Connection' => 'keep-alive'), '', '1.0');

        $server = $this->createStreamingServer(function () {
            return new Response();
        });

        $parser = $this->getMockBuilder('React\Http\Io\RequestHeaderParser')->disableOriginalConstructor()->getMock();
        $parser->expects($this->exactly(2))->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        $ref->setAccessible(true);
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write');
        $this->connection->expects($this->never())->method('end');

        // pretend parser just finished parsing
        $server->handleRequest($this->connection, $request);
    }

    public function testNewConnectionWillInvokeParserOnceAfterInvokingRequestHandlerWhenStreamingResponseBodyKeepsStreaming()
    {
        $request = new ServerRequest('GET', 'http://localhost/');

        $body = new ThroughStream();
        $server = $this->createStreamingServer(function () use ($body) {
            return new Response(200, array(), $body);
        });

        $parser = $this->getMockBuilder('React\Http\Io\RequestHeaderParser')->disableOriginalConstructor()->getMock();
        $parser->expects($this->once())->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        $ref->setAccessible(true);
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $this->connection->expects($this->once())->method('write');
        $this->connection->expects($this->never())->method('end');

        // pretend parser just finished parsing
        $server->handleRequest($this->connection, $request);
    }

    public function testNewConnectionWillInvokeParserTwiceAfterInvokingRequestHandlerWhenStreamingResponseBodyEnds()
    {
        $request = new ServerRequest('GET', 'http://localhost/');

        $body = new ThroughStream();
        $server = $this->createStreamingServer(function () use ($body) {
            return new Response(200, array(), $body);
        });

        $parser = $this->getMockBuilder('React\Http\Io\RequestHeaderParser')->disableOriginalConstructor()->getMock();
        $parser->expects($this->exactly(2))->method('handle');

        $ref = new \ReflectionProperty($server, 'parser');
        $ref->setAccessible(true);
        $ref->setValue($server, $parser);

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $this->connection->expects($this->exactly(2))->method('write');
        $this->connection->expects($this->never())->method('end');

        // pretend parser just finished parsing
        $server->handleRequest($this->connection, $request);

        $this->assertCount(2, $this->connection->listeners('close'));
        $body->end();
        $this->assertCount(1, $this->connection->listeners('close'));
    }

    private function createGetRequest()
    {
        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com\r\n";
        $data .= "Connection: close\r\n";
        $data .= "\r\n";

        return $data;
    }

    private function createStreamingServer($requestHandler)
    {
        return new StreamingServer(Loop::get(), $requestHandler);
    }
}
