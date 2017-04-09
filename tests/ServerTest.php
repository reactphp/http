<?php

namespace React\Tests\Http;

use React\Http\Server;
use Psr\Http\Message\RequestInterface;
use React\Http\Response;
use React\Stream\ReadableStream;
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
        $server = new Server($this->socket, $this->expectCallableNever());

        $this->socket->emit('connection', array($this->connection));

        $data = '';
        $data .= "GET / HTTP/1.1\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testRequestEventIsEmitted()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
            return \React\Promise\resolve(new Response());
        });

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestEvent()
    {
        $i = 0;
        $requestAssertion = null;
        $server = new Server($this->socket, function (RequestInterface $request) use (&$i, &$requestAssertion) {
            $i++;
            $requestAssertion = $request;
            return \React\Promise\resolve(new Response());
        });

        $this->connection
            ->expects($this->once())
            ->method('getRemoteAddress')
            ->willReturn('127.0.0.1');

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));

        $this->assertSame(1, $i);
        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('/', $requestAssertion->getRequestTarget());
        $this->assertSame('/', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com/', (string)$requestAssertion->getUri());
        $this->assertSame('example.com:80', $requestAssertion->getHeaderLine('Host'));
        $this->assertSame('127.0.0.1', $requestAssertion->remoteAddress);
    }

    public function testRequestGetWithHostAndCustomPort()
    {
        $requestAssertion = null;
        $server = new Server($this->socket, function (RequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

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
        $server = new Server($this->socket, function (RequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

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
        $server = new Server($this->socket, function (RequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: example.com:80\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('GET', $requestAssertion->getMethod());
        $this->assertSame('/', $requestAssertion->getRequestTarget());
        $this->assertSame('/', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com/', (string)$requestAssertion->getUri());
        $this->assertSame(null, $requestAssertion->getUri()->getPort());
        $this->assertSame('example.com:80', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestOptionsAsterisk()
    {
        $requestAssertion = null;
        $server = new Server($this->socket, function (RequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

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
        $server = new Server($this->socket, $this->expectCallableNever());
        $server->on('error', $this->expectCallableOnce());

        $this->socket->emit('connection', array($this->connection));

        $data = "GET * HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testRequestConnectAuthorityForm()
    {
        $requestAssertion = null;
        $server = new Server($this->socket, function (RequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $this->socket->emit('connection', array($this->connection));

        $data = "CONNECT example.com:443 HTTP/1.1\r\nHost: example.com:443\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('CONNECT', $requestAssertion->getMethod());
        $this->assertSame('example.com:443', $requestAssertion->getRequestTarget());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com:443', (string)$requestAssertion->getUri());
        $this->assertSame(443, $requestAssertion->getUri()->getPort());
        $this->assertSame('example.com:443', $requestAssertion->getHeaderLine('host'));
    }

    public function testRequestConnectAuthorityFormWithDefaultPortWillBeIgnored()
    {
        $requestAssertion = null;
        $server = new Server($this->socket, function (RequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $this->socket->emit('connection', array($this->connection));

        $data = "CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('CONNECT', $requestAssertion->getMethod());
        $this->assertSame('example.com:80', $requestAssertion->getRequestTarget());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com', (string)$requestAssertion->getUri());
        $this->assertSame(null, $requestAssertion->getUri()->getPort());
        $this->assertSame('example.com:80', $requestAssertion->getHeaderLine('Host'));
    }

    public function testRequestConnectAuthorityFormNonMatchingHostWillBePassedAsIs()
    {
        $requestAssertion = null;
        $server = new Server($this->socket, function (RequestInterface $request) use (&$requestAssertion) {
            $requestAssertion = $request;
            return new Response();
        });

        $this->socket->emit('connection', array($this->connection));

        $data = "CONNECT example.com:80 HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RingCentral\Psr7\Request', $requestAssertion);
        $this->assertSame('CONNECT', $requestAssertion->getMethod());
        $this->assertSame('example.com:80', $requestAssertion->getRequestTarget());
        $this->assertSame('', $requestAssertion->getUri()->getPath());
        $this->assertSame('http://example.com', (string)$requestAssertion->getUri());
        $this->assertSame(null, $requestAssertion->getUri()->getPort());
        $this->assertSame('example.com', $requestAssertion->getHeaderLine('host'));
    }

    public function testRequestNonConnectWithAuthorityRequestTargetWillReject()
    {
        $server = new Server($this->socket, $this->expectCallableNever());
        $server->on('error', $this->expectCallableOnce());

        $this->socket->emit('connection', array($this->connection));

        $data = "GET example.com:80 HTTP/1.1\r\nHost: example.com\r\n\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testRequestPauseWillbeForwardedToConnection()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
            $request->getBody()->pause();
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->once())->method('pause');
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
        $server = new Server($this->socket, function (RequestInterface $request) {
            $request->getBody()->resume();
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->once())->method('resume');
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestCloseWillPauseConnection()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
            $request->getBody()->close();
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->once())->method('pause');
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestPauseAfterCloseWillNotBeForwarded()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
            $request->getBody()->close();
            $request->getBody()->pause();#

            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->once())->method('pause');
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestResumeAfterCloseWillNotBeForwarded()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
            $request->getBody()->close();
            $request->getBody()->resume();

            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->once())->method('pause');
        $this->connection->expects($this->never())->method('resume');
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestEventWithoutBodyWillNotEmitData()
    {
        $never = $this->expectCallableNever();

        $server = new Server($this->socket, function (RequestInterface $request) use ($never) {
            $request->getBody()->on('data', $never);

            return \React\Promise\resolve(new Response());
        });

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestEventWithSecondDataEventWillEmitBodyData()
    {
        $once = $this->expectCallableOnceWith('incomplete');

        $server = new Server($this->socket, function (RequestInterface $request) use ($once) {
            $request->getBody()->on('data', $once);

            return \React\Promise\resolve(new Response());
        });

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

        $server = new Server($this->socket, function (RequestInterface $request) use ($once) {
            $request->getBody()->on('data', $once);

            return \React\Promise\resolve(new Response());
        });

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
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));

        $this->assertContains("\r\nX-Powered-By: React/alpha\r\n", $buffer);
    }

    public function testResponseContainsSameRequestProtocolVersionAndChunkedBodyForHttp11()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContains("bye", $buffer);
    }

    public function testResponseContainsSameRequestProtocolVersionAndRawBodyForHttp10()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.0 200 OK\r\n", $buffer);
        $this->assertContains("\r\n\r\n", $buffer);
        $this->assertContains("bye", $buffer);
    }

    public function testResponseContainsNoResponseBodyForHeadRequest()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = "HEAD / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertNotContains("bye", $buffer);
    }

    public function testResponseContainsNoResponseBodyAndNoContentLengthForNoContentStatus()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 204 No Content\r\n", $buffer);
        $this->assertNotContains("\r\n\Content-Length: 3\r\n", $buffer);
        $this->assertNotContains("bye", $buffer);
    }

    public function testResponseContainsNoResponseBodyForNotModifiedStatus()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
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
        $server = new Server($this->socket, $this->expectCallableNever());
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

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.2\r\nHost: localhost\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('InvalidArgumentException', $error);

        $this->assertContains("HTTP/1.1 505 HTTP Version not supported\r\n", $buffer);
        $this->assertContains("\r\n\r\n", $buffer);
        $this->assertContains("Error 505: HTTP Version Not Supported", $buffer);
    }

    public function testRequestOverflowWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = new Server($this->socket, $this->expectCallableNever());
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
        $server = new Server($this->socket, $this->expectCallableNever());
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

        $server = new Server($this->socket, function (RequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
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
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();
        $requestValidation = null;

        $server = new Server($this->socket, function (RequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent, &$requestValidation) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
            $requestValidation = $request;

            return \React\Promise\resolve(new Response());
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

        $this->assertFalse($requestValidation->hasHeader('Transfer-Encoding'));
    }

    public function testChunkedEncodedRequestAdditionalDataWontBeEmitted()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server($this->socket, function (RequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
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
        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server($this->socket, function (RequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
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

    public function testChunkedIsUpperCase()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server($this->socket, function (RequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
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

    public function testChunkedIsMixedUpperAndLowerCase()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server($this->socket, function (RequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
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

    public function testRequestHttp11WithoutHostWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = new Server($this->socket, $this->expectCallableNever());
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

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('InvalidArgumentException', $error);

        $this->assertContains("HTTP/1.1 400 Bad Request\r\n", $buffer);
        $this->assertContains("\r\n\r\nError 400: Bad Request", $buffer);
    }

    public function testRequestHttp11WithMalformedHostWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = new Server($this->socket, $this->expectCallableNever());
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

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: ///\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('InvalidArgumentException', $error);

        $this->assertContains("HTTP/1.1 400 Bad Request\r\n", $buffer);
        $this->assertContains("\r\n\r\nError 400: Bad Request", $buffer);
    }

    public function testRequestHttp11WithInvalidHostUriComponentsWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = new Server($this->socket, $this->expectCallableNever());
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

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\nHost: localhost:80/test\r\n\r\n";
        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('InvalidArgumentException', $error);

        $this->assertContains("HTTP/1.1 400 Bad Request\r\n", $buffer);
        $this->assertContains("\r\n\r\nError 400: Bad Request", $buffer);
    }

    public function testRequestHttp10WithoutHostEmitsRequestWithNoError()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
            return \React\Promise\resolve(new Response());
        });
        $server->on('error', $this->expectCallableNever());

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testWontEmitFurtherDataWhenContentLengthIsReached()
    {
        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server = new Server($this->socket, function (RequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

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


        $server = new Server($this->socket, function (RequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });


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

        $server = new Server($this->socket, function (RequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

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

        $server = new Server($this->socket, function (RequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

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

        $server = new Server($this->socket, function (RequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

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
        $server = new Server($this->socket, function (RequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent, &$requestValidation) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
            $requestValidation = $request;

            return \React\Promise\resolve(new Response());
        });

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
        $server = new Server($this->socket, function (RequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent, &$requestValidation) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);
            $requestValidation = $request;

            return \React\Promise\resolve(new Response());
        });

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
        $server = new Server($this->socket, $this->expectCallableNever());
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
        $server = new Server($this->socket, $this->expectCallableNever());
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
        $server = new Server($this->socket, $this->expectCallableNever());
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
        $server = new Server($this->socket, function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->once())->method('pause');

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
        $server = new Server($this->socket, function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->once())->method('pause');

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
        $server = new Server($this->socket, function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->once())->method('pause');

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
        $server = new Server($this->socket, function ($request) use ($errorEvent){
            $request->getBody()->on('error', $errorEvent);
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->once())->method('pause');

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
        $server = new Server($this->socket, function (RequestInterface $request) {
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->once())->method('pause');

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
        $server = new Server($this->socket, function (RequestInterface $request) {
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->once())->method('pause');

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
        $server = new Server($this->socket, function ($request) {
            $request->getBody()->close();
            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->never())->method('close');
        $this->connection->expects($this->once())->method('pause');

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

        $server = new Server($this->socket, function ($request) use ($dataEvent, $closeEvent, $endEvent, $errorEvent){
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

        $this->connection->expects($this->once())->method('pause');
        $this->connection->expects($this->never())->method('close');

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

        $server = new Server($this->socket, function (RequestInterface $request) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->getBody()->on('data', $dataEvent);
            $request->getBody()->on('end', $endEvent);
            $request->getBody()->on('close', $closeEvent);
            $request->getBody()->on('error', $errorEvent);

            return \React\Promise\resolve(new Response());
        });

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $data .= "hello world";

        $this->connection->emit('data', array($data));
    }

    public function testResponseWillBeChunkDecodedByDefault()
    {
        $stream = new ReadableStream();
        $server = new Server($this->socket, function (RequestInterface $request) use ($stream) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));
        $stream->emit('data', array('hello'));

        $this->assertContains("Transfer-Encoding: chunked", $buffer);
        $this->assertContains("hello", $buffer);
    }

    public function testContentLengthWillBeRemovedForResponseStream()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertNotContains("Transfer-Encoding: chunked", $buffer);
        $this->assertContains("Content-Length: 5", $buffer);
        $this->assertContains("hello", $buffer);
    }

    public function testOnlyAllowChunkedEncoding()
    {
        $stream = new ReadableStream();
        $server = new Server($this->socket, function (RequestInterface $request) use ($stream) {
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
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContains("Date:", $buffer);
        $this->assertContains("\r\n\r\n", $buffer);
    }

    public function testAddCustomDateHeader()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContains("Date: Tue, 15 Nov 1994 08:12:31 GMT\r\n", $buffer);
        $this->assertContains("\r\n\r\n", $buffer);
    }

    public function testRemoveDateHeader()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $server = new Server($this->socket, $this->expectCallableNever());
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

        $server = new Server($this->socket, $this->expectCallableNever());
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
        $server = new Server($this->socket, function (RequestInterface $request) {
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
        $server = new Server($this->socket, function (RequestInterface $request) {
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
        $server = new Server($this->socket, function (RequestInterface $request) {
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
        $server = new Server($this->socket, 'invalid');
    }

    public function testHttpBodyStreamAsBodyWillStreamData()
    {
        $input = new ReadableStream();

        $server = new Server($this->socket, function (RequestInterface $request) use ($input) {
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
        $input = new ReadableStream();

        $server = new Server($this->socket, function (RequestInterface $request) use ($input) {
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
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));
        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
        $this->assertContains("\r\n\r\n", $buffer);
    }

    public function testReturnInvalidTypeWillResultInError()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertInstanceOf('RuntimeException', $exception);
    }

    public function testResolveWrongTypeInPromiseWillResultInError()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
    }

    public function testRejectedPromiseWillResultInErrorMessage()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
    }

    public function testExcpetionInCallbackWillResultInErrorMessage()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
    }

    public function testHeaderWillAlwaysBeContentLengthForStringBody()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
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
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 200 OK\r\n", $buffer);
    }

    public function testExceptionThrowInCallBackFunctionWillResultInErrorMessage()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertInstanceOf('RuntimeException', $exception);
        $this->assertContains("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertEquals('hello', $exception->getPrevious()->getMessage());
    }

    public function testRejectOfNonExceptionWillResultInErrorMessage()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
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

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));

        $this->assertContains("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertInstanceOf('RuntimeException', $exception);
    }

    private function getUpgradeHeader()
    {
        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: localhost\r\n";
        $data .= "Connection: Upgrade\r\n";
        $data .= "Upgrade: echo\r\n\r\n";

        return $data;
    }

    public function testConnectionUpgradeEcho()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
            $responseStream = new ReadableStream();
            $request->getBody()->on('data', function ($data) use ($responseStream) {
                $responseStream->emit('data', [$data]);
            });

            $this->assertEquals('Upgrade', $request->getHeaderLine('Connection'));
            $this->assertEquals('echo', $request->getHeaderLine('Upgrade'));

            $response = new Response(
                101,
                array(
                    'Connection' => 'Upgrade',
                    'Upgrade'    => 'echo'
                ),
                $responseStream);
            return $response;
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

        $this->connection->emit('data', array($this->getUpgradeHeader()));

        $this->connection->emit('data', array('text to be echoed'));

        $this->assertStringStartsWith("HTTP/1.1 101 Switching Protocols\r\n", $buffer);
        $this->assertContains("\r\nConnection: Upgrade\r\n", $buffer);
        $this->assertContains("\r\nUpgrade: echo\r\n", $buffer);
        $this->assertStringEndsWith("\r\n\r\ntext to be echoed", $buffer);
    }

    public function testUpgradeWithNoProtocolRespondsWithError()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
            $this->fail('Callback should not be called');
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

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: localhost\r\n";
        $data .= "Connection: Upgrade\r\n\r\n";

        $this->connection->emit('data', array($this->getUpgradeHeader()));

        $this->assertStringStartsWith("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertInstanceOf('RuntimeException', $exception);
    }

    public function testUpgrade101MustContainUpgradeHeaderWithNewProtocol()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
            $responseStream = new ReadableStream();
            $this->assertEquals('Upgrade', $request->getHeaderLine('Connection'));
            $this->assertEquals('echo', $request->getHeaderLine('Upgrade'));

            $response = new Response(
                101,
                array(
                    'Connection' => 'Upgrade'
                ),
                $responseStream);
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
            ->will(
                $this->returnCallback(
                    function ($data) use (&$buffer) {
                        $buffer .= $data;
                    }
                )
            );

        $this->socket->emit('connection', array($this->connection));

        $this->connection->emit('data', array($this->getUpgradeHeader()));

        $this->assertStringStartsWith("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertInstanceOf('RuntimeException', $exception);
    }

    public function testUpgradeProtocolMustBeOneRequested()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
            $responseStream = new ReadableStream();
            $this->assertEquals('Upgrade', $request->getHeaderLine('Connection'));
            $this->assertEquals('echo', $request->getHeaderLine('Upgrade'));

            $response = new Response(
                101,
                array(
                    'Connection' => 'Upgrade',
                    'Upgrade'    => 'notecho'
                ),
                $responseStream);
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
            ->will(
                $this->returnCallback(
                    function ($data) use (&$buffer) {
                        $buffer .= $data;
                    }
                )
            );

        $this->socket->emit('connection', array($this->connection));

        $this->connection->emit('data', array($this->getUpgradeHeader()));

        $this->assertStringStartsWith("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertInstanceOf('RuntimeException', $exception);
    }

    public function testUpgrade426WithUpgradeHeader()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
            $response = new Response(
                426,
                array(
                    'Upgrade' => 'something'
                ));
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
            ->will(
                $this->returnCallback(
                    function ($data) use (&$buffer) {
                        $buffer .= $data;
                    }
                )
            );

        $this->socket->emit('connection', array($this->connection));

        $this->connection->emit('data', array($this->getUpgradeHeader()));

        $this->assertStringStartsWith("HTTP/1.1 426 Upgrade Required\r\n", $buffer);
    }

    public function testUpgrade426MustContainUpgradeHeaderWithProtocol()
    {
        $server = new Server($this->socket, function (RequestInterface $request) {
            $response = new Response(
                426,
                array());
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
            ->will(
                $this->returnCallback(
                    function ($data) use (&$buffer) {
                        $buffer .= $data;
                    }
                )
            );

        $this->socket->emit('connection', array($this->connection));

        $this->connection->emit('data', array($this->getUpgradeHeader()));

        $this->assertStringStartsWith("HTTP/1.1 500 Internal Server Error\r\n", $buffer);
        $this->assertInstanceOf('RuntimeException', $exception);
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
