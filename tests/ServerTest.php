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
                    'getLocalAddress',
                    'pipe'
                )
            )
            ->getMock();

        $this->socket = new SocketServerStub();
    }

    public function testRequestEventWillNotBeEmittedForIncompleteHeaders()
    {
        $server = new Server($this->socket);
        $server->on('request', $this->expectCallableNever());

        $this->socket->emit('connection', array($this->connection));

        $data = '';
        $data .= "GET / HTTP/1.1\r\n";
        $this->connection->emit('data', array($data));
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
        $this->assertSame(array(), $requestAssertion->getQueryParams());
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

        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "Content-Length: 5\r\n";
        $data .= "\r\n";

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

    public function testRequestCloseWillPauseConnection()
    {
        $server = new Server($this->socket);
        $server->on('request', function (Request $request) {
            $request->close();
        });

        $this->connection->expects($this->once())->method('pause');
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestPauseAfterCloseWillNotBeForwarded()
    {
        $server = new Server($this->socket);
        $server->on('request', function (Request $request) {
            $request->close();
            $request->pause();
        });

        $this->connection->expects($this->once())->method('pause');
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestResumeAfterCloseWillNotBeForwarded()
    {
        $server = new Server($this->socket);
        $server->on('request', function (Request $request) {
            $request->close();
            $request->resume();
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

        $server = new Server($this->socket);
        $server->on('request', function (Request $request) use ($never) {
            $request->on('data', $never);
        });

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestEventWithSecondDataEventWillEmitBodyData()
    {
        $once = $this->expectCallableOnceWith('incomplete');

        $server = new Server($this->socket);
        $server->on('request', function (Request $request) use ($once) {
            $request->on('data', $once);
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

        $server = new Server($this->socket);
        $server->on('request', function (Request $request) use ($once) {
            $request->on('data', $once);
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

    public function testClosingResponseDoesNotSendAnyData()
    {
        $server = new Server($this->socket);
        $server->on('request', function (Request $request, Response $response) {
            $response->close();
        });

        $this->connection->expects($this->never())->method('write');
        $this->connection->expects($this->never())->method('end');
        $this->connection->expects($this->once())->method('close');

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testResponseContainsSameRequestProtocolVersionAndChunkedBodyForHttp11()
    {
        $server = new Server($this->socket);
        $server->on('request', function (Request $request, Response $response) {
            $response->writeHead();
            $response->end('bye');
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
        $this->assertContains("\r\n\r\n3\r\nbye\r\n0\r\n\r\n", $buffer);
    }

    public function testResponseContainsSameRequestProtocolVersionAndRawBodyForHttp10()
    {
        $server = new Server($this->socket);
        $server->on('request', function (Request $request, Response $response) {
            $response->writeHead();
            $response->end('bye');
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
        $this->assertContains("\r\n\r\nbye", $buffer);
    }

    public function testRequestInvalidHttpProtocolVersionWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = new Server($this->socket);
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

        $this->assertContains("HTTP/1.1 505 HTTP Version Not Supported\r\n", $buffer);
        $this->assertContains("\r\n\r\nError 505: HTTP Version Not Supported", $buffer);
    }

    public function testServerWithNoRequestListenerDoesNotSendAnythingToConnection()
    {
        $server = new Server($this->socket);

        $this->connection
            ->expects($this->never())
            ->method('write');

        $this->connection
            ->expects($this->never())
            ->method('end');

        $this->connection
            ->expects($this->never())
            ->method('close');

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $this->connection->emit('data', array($data));
    }

    public function testRequestOverflowWillEmitErrorAndSendErrorResponse()
    {
        $error = null;
        $server = new Server($this->socket);
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
        $server = new Server($this->socket);
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
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
            $request->on('close', $closeEvent);
            $request->on('error', $errorEvent);
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
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();
        $requestValidation = null;

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent, $closeEvent, $errorEvent, &$requestValidation) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
            $request->on('close', $closeEvent);
            $request->on('error', $errorEvent);
            $requestValidation = $request;
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
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
            $request->on('close', $closeEvent);
            $request->on('error', $errorEvent);
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
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
            $request->on('close', $closeEvent);
            $request->on('error', $errorEvent);
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
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
            $request->on('close', $closeEvent);
            $request->on('error', $errorEvent);
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
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
            $request->on('close', $closeEvent);
            $request->on('error', $errorEvent);
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
        $server = new Server($this->socket);
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
        $server = new Server($this->socket);
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
        $server = new Server($this->socket);
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
        $server = new Server($this->socket);
        $server->on('request', $this->expectCallableOnce());
        $server->on('error', $this->expectCallableNever());

        $this->socket->emit('connection', array($this->connection));

        $data = "GET / HTTP/1.0\r\n\r\n";
        $this->connection->emit('data', array($data));
    }

    public function testWontEmitFurtherDataWhenContentLengthIsReached()
    {
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
            $request->on('close', $closeEvent);
            $request->on('error', $errorEvent);
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
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
            $request->on('close', $closeEvent);
            $request->on('error', $errorEvent);
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
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
            $request->on('close', $closeEvent);
            $request->on('error', $errorEvent);
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
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
            $request->on('close', $closeEvent);
            $request->on('error', $errorEvent);
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
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
            $request->on('close', $closeEvent);
            $request->on('error', $errorEvent);
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
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $requestValidation = null;
        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent, $closeEvent, $errorEvent, &$requestValidation) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
            $request->on('close', $closeEvent);
            $request->on('error', $errorEvent);
            $requestValidation = $request;
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
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableOnceWith('hello');
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $requestValidation = null;
        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent, $closeEvent, $errorEvent, &$requestValidation) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
            $request->on('close', $closeEvent);
            $request->on('error', $errorEvent);
            $requestValidation = $request;
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
        $server = new Server($this->socket);
        $server->on('request', $this->expectCallableNever());
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

    public function testMultipleIntegerInContentLengthWillLeadToError()
    {
        $error = null;
        $server = new Server($this->socket);
        $server->on('request', $this->expectCallableNever());
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
        $server = new Server($this->socket);
        $server->on('request', function ($request, $response) use ($errorEvent){
            $request->on('error', $errorEvent);
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
        $server = new Server($this->socket);
        $server->on('request', function ($request, $response) use ($errorEvent){
            $request->on('error', $errorEvent);
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
        $server = new Server($this->socket);
        $server->on('request', function ($request, $response) use ($errorEvent){
            $request->on('error', $errorEvent);
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
        $server = new Server($this->socket);
        $server->on('request', function ($request, $response) use ($errorEvent){
            $request->on('error', $errorEvent);
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
        $server = new Server($this->socket);
        $server->on('request', $this->expectCallableOnce());

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
        $server = new Server($this->socket);
        $server->on('request', $this->expectCallableOnce());

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
        $server = new Server($this->socket);
        $server->on('request', function ($request, $response) {
            $request->close();
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

        $server = new Server($this->socket);
        $server->on('request', function ($request, $response) use ($dataEvent, $closeEvent, $endEvent, $errorEvent){
            $request->on('data', $dataEvent);
            $request->on('close', $closeEvent);
            $request->on('end', $endEvent);
            $request->on('error', $errorEvent);
        });

        $this->connection->expects($this->once())->method('pause');
        $this->connection->expects($this->never())->method('close');

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));
    }

    public function testRequestWithoutDefinedLengthWillIgnoreDataEvent()
    {
        $server = new Server($this->socket);

        $dataEvent = $this->expectCallableNever();
        $endEvent = $this->expectCallableOnce();
        $closeEvent = $this->expectCallableOnce();
        $errorEvent = $this->expectCallableNever();

        $server->on('request', function (Request $request, Response $response) use ($dataEvent, $endEvent, $closeEvent, $errorEvent) {
            $request->on('data', $dataEvent);
            $request->on('end', $endEvent);
            $request->on('close', $closeEvent);
            $request->on('error', $errorEvent);
        });

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();
        $data .= "hello world";

        $this->connection->emit('data', array($data));
    }

    public function testResponseWillBeChunkDecodedByDefault()
    {
        $server = new Server($this->socket);

        $server->on('request', function (Request $request, Response $response) {
            $response->writeHead();
            $response->write('hello');
        });

        $this->connection
            ->expects($this->exactly(2))
            ->method('write')
            ->withConsecutive(
                array($this->anything()),
                array("5\r\nhello\r\n")
            );

        $this->socket->emit('connection', array($this->connection));

        $data = $this->createGetRequest();

        $this->connection->emit('data', array($data));
    }

    public function testContentLengthWillBeRemovedForResponseStream()
    {
        $server = new Server($this->socket);

        $server->on('request', function (Request $request, Response $response) {
            $response->writeHead(
                200,
                array(
                    'Content-Length' => 4,
                    'Transfer-Encoding' => 'chunked'
                )
            );

            $response->write('hello');
        });

        $buffer = '';
        $this->connection
            ->expects($this->exactly(2))
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
        $this->assertContains("Content-Length: 4", $buffer);
        $this->assertContains("hello", $buffer);
    }

    public function testOnlyAllowChunkedEncoding()
    {
        $server = new Server($this->socket);

        $server->on('request', function (Request $request, Response $response) {
            $response->writeHead(
                200,
                array(
                    'Transfer-Encoding' => 'custom'
                )
            );

            $response->write('hello');
        });

        $buffer = '';
        $this->connection
        ->expects($this->exactly(2))
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

        $this->assertContains('Transfer-Encoding: chunked', $buffer);
        $this->assertNotContains('Transfer-Encoding: custom', $buffer);
        $this->assertContains("5\r\nhello\r\n", $buffer);
    }

    public function testDateHeaderWillBeAddedWhenNoneIsGiven()
    {
        $server = new Server($this->socket);

        $server->on('request', function (Request $request, Response $response) {
            $response->writeHead(200);
        });

        $buffer = '';
            $this->connection
            ->expects($this->once())
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
        $server = new Server($this->socket);

        $server->on('request', function (Request $request, Response $response) {
            $response->writeHead(200, array("Date" => "Tue, 15 Nov 1994 08:12:31 GMT"));
        });

        $buffer = '';
        $this->connection
            ->expects($this->once())
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
        $server = new Server($this->socket);

        $server->on('request', function (Request $request, Response $response) {
            $response->writeHead(200, array('Date' => array()));
        });

        $buffer = '';
        $this->connection
            ->expects($this->once())
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

        $server = new Server($this->socket);
        $server->on('request', $this->expectCallableNever());
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

    private function createGetRequest()
    {
        $data = "GET / HTTP/1.1\r\n";
        $data .= "Host: example.com:80\r\n";
        $data .= "Connection: close\r\n";
        $data .= "\r\n";

        return $data;
    }
}
