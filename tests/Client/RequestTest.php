<?php

namespace React\Tests\Http\Client;

use React\Http\Client\Request;
use React\Http\Client\RequestData;
use React\Stream\DuplexResourceStream;
use React\Promise\RejectedPromise;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Tests\Http\TestCase;

class RequestTest extends TestCase
{
    private $connector;
    private $stream;

    /**
     * @before
     */
    public function setUpStream()
    {
        $this->stream = $this->getMockBuilder('React\Socket\ConnectionInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $this->connector = $this->getMockBuilder('React\Socket\ConnectorInterface')
            ->getMock();

        $this->response = $this->getMockBuilder('React\Http\Client\Response')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /** @test */
    public function requestShouldBindToStreamEventsAndUseconnector()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $this->successfulConnectionMock();

        $this->stream
            ->expects($this->at(0))
            ->method('on')
            ->with('drain', $this->identicalTo(array($request, 'handleDrain')));
        $this->stream
            ->expects($this->at(1))
            ->method('on')
            ->with('data', $this->identicalTo(array($request, 'handleData')));
        $this->stream
            ->expects($this->at(2))
            ->method('on')
            ->with('end', $this->identicalTo(array($request, 'handleEnd')));
        $this->stream
            ->expects($this->at(3))
            ->method('on')
            ->with('error', $this->identicalTo(array($request, 'handleError')));
        $this->stream
            ->expects($this->at(4))
            ->method('on')
            ->with('close', $this->identicalTo(array($request, 'handleClose')));
        $this->stream
            ->expects($this->at(6))
            ->method('removeListener')
            ->with('drain', $this->identicalTo(array($request, 'handleDrain')));
        $this->stream
            ->expects($this->at(7))
            ->method('removeListener')
            ->with('data', $this->identicalTo(array($request, 'handleData')));
        $this->stream
            ->expects($this->at(8))
            ->method('removeListener')
            ->with('end', $this->identicalTo(array($request, 'handleEnd')));
        $this->stream
            ->expects($this->at(9))
            ->method('removeListener')
            ->with('error', $this->identicalTo(array($request, 'handleError')));
        $this->stream
            ->expects($this->at(10))
            ->method('removeListener')
            ->with('close', $this->identicalTo(array($request, 'handleClose')));

        $response = $this->response;

        $this->stream->expects($this->once())
            ->method('emit')
            ->with('data', $this->identicalTo(array('body')));

        $response->expects($this->at(0))
            ->method('on')
            ->with('close', $this->anything())
            ->will($this->returnCallback(function ($event, $cb) use (&$endCallback) {
                $endCallback = $cb;
            }));

        $factory = $this->createCallableMock();
        $factory->expects($this->once())
            ->method('__invoke')
            ->with('HTTP', '1.0', '200', 'OK', array('Content-Type' => 'text/plain'))
            ->will($this->returnValue($response));

        $request->setResponseFactory($factory);

        $handler = $this->createCallableMock();
        $handler->expects($this->once())
            ->method('__invoke')
            ->with($response);

        $request->on('response', $handler);
        $request->on('end', $this->expectCallableNever());

        $handler = $this->createCallableMock();
        $handler->expects($this->once())
            ->method('__invoke');

        $request->on('close', $handler);
        $request->end();

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");

        $this->assertNotNull($endCallback);
        call_user_func($endCallback);
    }

    /** @test */
    public function requestShouldEmitErrorIfConnectionFails()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $this->rejectedConnectionMock();

        $handler = $this->createCallableMock();
        $handler->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->isInstanceOf('RuntimeException')
            );

        $request->on('error', $handler);

        $handler = $this->createCallableMock();
        $handler->expects($this->once())
            ->method('__invoke');

        $request->on('close', $handler);
        $request->on('end', $this->expectCallableNever());

        $request->end();
    }

    /** @test */
    public function requestShouldEmitErrorIfConnectionClosesBeforeResponseIsParsed()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $this->successfulConnectionMock();

        $handler = $this->createCallableMock();
        $handler->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->isInstanceOf('RuntimeException')
            );

        $request->on('error', $handler);

        $handler = $this->createCallableMock();
        $handler->expects($this->once())
            ->method('__invoke');

        $request->on('close', $handler);
        $request->on('end', $this->expectCallableNever());

        $request->end();
        $request->handleEnd();
    }

    /** @test */
    public function requestShouldEmitErrorIfConnectionEmitsError()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $this->successfulConnectionMock();

        $handler = $this->createCallableMock();
        $handler->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->isInstanceOf('Exception')
            );

        $request->on('error', $handler);

        $handler = $this->createCallableMock();
        $handler->expects($this->once())
            ->method('__invoke');

        $request->on('close', $handler);
        $request->on('end', $this->expectCallableNever());

        $request->end();
        $request->handleError(new \Exception('test'));
    }

    /** @test */
    public function requestShouldEmitErrorIfGuzzleParseThrowsException()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $this->successfulConnectionMock();

        $handler = $this->createCallableMock();
        $handler->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->isInstanceOf('\InvalidArgumentException')
            );

        $request->on('error', $handler);

        $request->end();
        $request->handleData("\r\n\r\n");
    }

    /**
     * @test
     */
    public function requestShouldEmitErrorIfUrlIsInvalid()
    {
        $requestData = new RequestData('GET', 'ftp://www.example.com');
        $request = new Request($this->connector, $requestData);

        $handler = $this->createCallableMock();
        $handler->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->isInstanceOf('\InvalidArgumentException')
            );

        $request->on('error', $handler);

        $this->connector->expects($this->never())
            ->method('connect');

        $request->end();
    }

    /**
     * @test
     */
    public function requestShouldEmitErrorIfUrlHasNoScheme()
    {
        $requestData = new RequestData('GET', 'www.example.com');
        $request = new Request($this->connector, $requestData);

        $handler = $this->createCallableMock();
        $handler->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->isInstanceOf('\InvalidArgumentException')
            );

        $request->on('error', $handler);

        $this->connector->expects($this->never())
            ->method('connect');

        $request->end();
    }

    /** @test */
    public function postRequestShouldSendAPostRequest()
    {
        $requestData = new RequestData('POST', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $this->successfulConnectionMock();

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\nUser-Agent:.*\r\n\r\nsome post data$#"));

        $factory = $this->createCallableMock();
        $factory->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue($this->response));

        $request->setResponseFactory($factory);
        $request->end('some post data');

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");
    }

    /** @test */
    public function writeWithAPostRequestShouldSendToTheStream()
    {
        $requestData = new RequestData('POST', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $this->successfulConnectionMock();

        $this->stream
            ->expects($this->at(5))
            ->method('write')
            ->with($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\nUser-Agent:.*\r\n\r\nsome$#"));
        $this->stream
            ->expects($this->at(6))
            ->method('write')
            ->with($this->identicalTo("post"));
        $this->stream
            ->expects($this->at(7))
            ->method('write')
            ->with($this->identicalTo("data"));

        $factory = $this->createCallableMock();
        $factory->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue($this->response));

        $request->setResponseFactory($factory);

        $request->write("some");
        $request->write("post");
        $request->end("data");

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");
    }

    /** @test */
    public function writeWithAPostRequestShouldSendBodyAfterHeadersAndEmitDrainEvent()
    {
        $requestData = new RequestData('POST', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $resolveConnection = $this->successfulAsyncConnectionMock();

        $this->stream
            ->expects($this->at(5))
            ->method('write')
            ->with($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\nUser-Agent:.*\r\n\r\nsomepost$#"))
            ->willReturn(true);
        $this->stream
            ->expects($this->at(6))
            ->method('write')
            ->with($this->identicalTo("data"));

        $factory = $this->createCallableMock();
        $factory->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue($this->response));

        $request->setResponseFactory($factory);

        $this->assertFalse($request->write("some"));
        $this->assertFalse($request->write("post"));

        $request->on('drain', $this->expectCallableOnce());
        $request->once('drain', function () use ($request) {
            $request->write("data");
            $request->end();
        });

        $resolveConnection();

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");
    }

    /** @test */
    public function writeWithAPostRequestShouldForwardDrainEventIfFirstChunkExceedsBuffer()
    {
        $requestData = new RequestData('POST', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $this->stream = $this->getMockBuilder('React\Socket\Connection')
            ->disableOriginalConstructor()
            ->setMethods(array('write'))
            ->getMock();

        $resolveConnection = $this->successfulAsyncConnectionMock();

        $this->stream
            ->expects($this->at(0))
            ->method('write')
            ->with($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\nUser-Agent:.*\r\n\r\nsomepost$#"))
            ->willReturn(false);
        $this->stream
            ->expects($this->at(1))
            ->method('write')
            ->with($this->identicalTo("data"));

        $factory = $this->createCallableMock();
        $factory->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue($this->response));

        $request->setResponseFactory($factory);

        $this->assertFalse($request->write("some"));
        $this->assertFalse($request->write("post"));

        $request->on('drain', $this->expectCallableOnce());
        $request->once('drain', function () use ($request) {
            $request->write("data");
            $request->end();
        });

        $resolveConnection();
        $this->stream->emit('drain');

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");
    }

    /** @test */
    public function pipeShouldPipeDataIntoTheRequestBody()
    {
        $requestData = new RequestData('POST', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $this->successfulConnectionMock();

        $this->stream
            ->expects($this->at(5))
            ->method('write')
            ->with($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\nUser-Agent:.*\r\n\r\nsome$#"));
        $this->stream
            ->expects($this->at(6))
            ->method('write')
            ->with($this->identicalTo("post"));
        $this->stream
            ->expects($this->at(7))
            ->method('write')
            ->with($this->identicalTo("data"));

        $factory = $this->createCallableMock();
        $factory->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue($this->response));

        $loop = $this
            ->getMockBuilder('React\EventLoop\LoopInterface')
            ->getMock();

        $request->setResponseFactory($factory);

        $stream = fopen('php://memory', 'r+');
        $stream = new DuplexResourceStream($stream, $loop);

        $stream->pipe($request);
        $stream->emit('data', array('some'));
        $stream->emit('data', array('post'));
        $stream->emit('data', array('data'));

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");
    }

    /**
     * @test
     */
    public function writeShouldStartConnecting()
    {
        $requestData = new RequestData('POST', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $this->connector->expects($this->once())
                        ->method('connect')
                        ->with('www.example.com:80')
                        ->willReturn(new Promise(function () { }));

        $request->write('test');
    }

    /**
     * @test
     */
    public function endShouldStartConnectingAndChangeStreamIntoNonWritableMode()
    {
        $requestData = new RequestData('POST', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $this->connector->expects($this->once())
                        ->method('connect')
                        ->with('www.example.com:80')
                        ->willReturn(new Promise(function () { }));

        $request->end();

        $this->assertFalse($request->isWritable());
    }

    /**
     * @test
     */
    public function closeShouldEmitCloseEvent()
    {
        $requestData = new RequestData('POST', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $request->on('close', $this->expectCallableOnce());
        $request->close();
    }

    /**
     * @test
     */
    public function writeAfterCloseReturnsFalse()
    {
        $requestData = new RequestData('POST', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $request->close();

        $this->assertFalse($request->isWritable());
        $this->assertFalse($request->write('nope'));
    }

    /**
     * @test
     */
    public function endAfterCloseIsNoOp()
    {
        $requestData = new RequestData('POST', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $this->connector->expects($this->never())
                        ->method('connect');

        $request->close();
        $request->end();
    }

    /**
     * @test
     */
    public function closeShouldCancelPendingConnectionAttempt()
    {
        $requestData = new RequestData('POST', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $promise = new Promise(function () {}, function () {
            throw new \RuntimeException();
        });

        $this->connector->expects($this->once())
            ->method('connect')
            ->with('www.example.com:80')
            ->willReturn($promise);

        $request->end();

        $request->on('error', $this->expectCallableNever());
        $request->on('close', $this->expectCallableOnce());

        $request->close();
        $request->close();
    }

    /** @test */
    public function requestShouldRelayErrorEventsFromResponse()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $this->successfulConnectionMock();

        $response = $this->response;

        $response->expects($this->at(0))
            ->method('on')
            ->with('close', $this->anything());
        $response->expects($this->at(1))
            ->method('on')
            ->with('error', $this->anything())
            ->will($this->returnCallback(function ($event, $cb) use (&$errorCallback) {
                $errorCallback = $cb;
            }));

        $factory = $this->createCallableMock();
        $factory->expects($this->once())
            ->method('__invoke')
            ->with('HTTP', '1.0', '200', 'OK', array('Content-Type' => 'text/plain'))
            ->will($this->returnValue($response));

        $request->setResponseFactory($factory);
        $request->end();

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");

        $this->assertNotNull($errorCallback);
        call_user_func($errorCallback, new \Exception('test'));
    }

    /** @test */
    public function requestShouldRemoveAllListenerAfterClosed()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $request->on('close', function () {});
        $this->assertCount(1, $request->listeners('close'));

        $request->close();
        $this->assertCount(0, $request->listeners('close'));
    }

    private function successfulConnectionMock()
    {
        call_user_func($this->successfulAsyncConnectionMock());
    }

    private function successfulAsyncConnectionMock()
    {
        $deferred = new Deferred();

        $this->connector
            ->expects($this->once())
            ->method('connect')
            ->with('www.example.com:80')
            ->will($this->returnValue($deferred->promise()));

        $stream = $this->stream;
        return function () use ($deferred, $stream) {
            $deferred->resolve($stream);
        };
    }

    private function rejectedConnectionMock()
    {
        $this->connector
            ->expects($this->once())
            ->method('connect')
            ->with('www.example.com:80')
            ->will($this->returnValue(new RejectedPromise(new \RuntimeException())));
    }

    /** @test */
    public function multivalueHeader()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $this->successfulConnectionMock();

        $response = $this->response;

        $response->expects($this->at(0))
        ->method('on')
        ->with('close', $this->anything());
        $response->expects($this->at(1))
        ->method('on')
        ->with('error', $this->anything())
        ->will($this->returnCallback(function ($event, $cb) use (&$errorCallback) {
            $errorCallback = $cb;
        }));

        $factory = $this->createCallableMock();
        $factory->expects($this->once())
        ->method('__invoke')
        ->with('HTTP', '1.0', '200', 'OK', array('Content-Type' => 'text/plain', 'X-Xss-Protection' => '1; mode=block', 'Cache-Control' => 'public, must-revalidate, max-age=0'))
        ->will($this->returnValue($response));

        $request->setResponseFactory($factory);
        $request->end();

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("X-Xss-Protection:1; mode=block\r\n");
        $request->handleData("Cache-Control:public, must-revalidate, max-age=0\r\n");
        $request->handleData("\r\nbody");

        $this->assertNotNull($errorCallback);
        call_user_func($errorCallback, new \Exception('test'));
    }

    /** @test */
    public function chunkedStreamDecoder()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new Request($this->connector, $requestData);

        $this->successfulConnectionMock();

        $request->end();

        $this->stream->expects($this->once())
            ->method('emit')
            ->with('data', array("1\r\nb\r"));

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Transfer-Encoding: chunked\r\n");
        $request->handleData("\r\n1\r\nb\r");
        $request->handleData("\n3\t\nody\r\n0\t\n\r\n");

    }
}
