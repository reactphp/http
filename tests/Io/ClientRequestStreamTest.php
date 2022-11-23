<?php

namespace React\Tests\Http\Io;

use React\Http\Client\RequestData;
use React\Http\Io\ClientRequestStream;
use React\Stream\DuplexResourceStream;
use React\Promise\RejectedPromise;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Tests\Http\TestCase;

class ClientRequestStreamTest extends TestCase
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
    }

    /** @test */
    public function requestShouldBindToStreamEventsAndUseconnector()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new ClientRequestStream($this->connector, $requestData);

        $this->successfulConnectionMock();

        $this->stream->expects($this->exactly(6))->method('on')->withConsecutive(
            array('drain', $this->identicalTo(array($request, 'handleDrain'))),
            array('data', $this->identicalTo(array($request, 'handleData'))),
            array('end', $this->identicalTo(array($request, 'handleEnd'))),
            array('error', $this->identicalTo(array($request, 'handleError'))),
            array('close', $this->identicalTo(array($request, 'handleClose')))
        );

        $this->stream->expects($this->exactly(5))->method('removeListener')->withConsecutive(
            array('drain', $this->identicalTo(array($request, 'handleDrain'))),
            array('data', $this->identicalTo(array($request, 'handleData'))),
            array('end', $this->identicalTo(array($request, 'handleEnd'))),
            array('error', $this->identicalTo(array($request, 'handleError'))),
            array('close', $this->identicalTo(array($request, 'handleClose')))
        );

        $request->on('end', $this->expectCallableNever());

        $request->end();

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");
    }

    /**
     * @test
     */
    public function requestShouldConnectViaTlsIfUrlUsesHttpsScheme()
    {
        $requestData = new RequestData('GET', 'https://www.example.com');
        $request = new ClientRequestStream($this->connector, $requestData);

        $this->connector->expects($this->once())->method('connect')->with('tls://www.example.com:443')->willReturn(new Promise(function () { }));

        $request->end();
    }

    /** @test */
    public function requestShouldEmitErrorIfConnectionFails()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new ClientRequestStream($this->connector, $requestData);

        $this->connector->expects($this->once())->method('connect')->willReturn(\React\Promise\reject(new \RuntimeException()));

        $request->on('error', $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));

        $request->on('close', $this->expectCallableOnce());
        $request->on('end', $this->expectCallableNever());

        $request->end();
    }

    /** @test */
    public function requestShouldEmitErrorIfConnectionClosesBeforeResponseIsParsed()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new ClientRequestStream($this->connector, $requestData);

        $this->successfulConnectionMock();

        $request->on('error', $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));

        $request->on('close', $this->expectCallableOnce());
        $request->on('end', $this->expectCallableNever());

        $request->end();
        $request->handleEnd();
    }

    /** @test */
    public function requestShouldEmitErrorIfConnectionEmitsError()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new ClientRequestStream($this->connector, $requestData);

        $this->successfulConnectionMock();

        $request->on('error', $this->expectCallableOnceWith($this->isInstanceOf('Exception')));

        $request->on('close', $this->expectCallableOnce());
        $request->on('end', $this->expectCallableNever());

        $request->end();
        $request->handleError(new \Exception('test'));
    }

    /** @test */
    public function requestShouldEmitErrorIfRequestParserThrowsException()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new ClientRequestStream($this->connector, $requestData);

        $this->successfulConnectionMock();

        $request->on('error', $this->expectCallableOnceWith($this->isInstanceOf('InvalidArgumentException')));

        $request->end();
        $request->handleData("\r\n\r\n");
    }

    /**
     * @test
     */
    public function requestShouldEmitErrorIfUrlIsInvalid()
    {
        $requestData = new RequestData('GET', 'ftp://www.example.com');
        $request = new ClientRequestStream($this->connector, $requestData);

        $request->on('error', $this->expectCallableOnceWith($this->isInstanceOf('InvalidArgumentException')));

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
        $request = new ClientRequestStream($this->connector, $requestData);

        $request->on('error', $this->expectCallableOnceWith($this->isInstanceOf('InvalidArgumentException')));

        $this->connector->expects($this->never())
            ->method('connect');

        $request->end();
    }

    /** @test */
    public function postRequestShouldSendAPostRequest()
    {
        $requestData = new RequestData('POST', 'http://www.example.com');
        $request = new ClientRequestStream($this->connector, $requestData);

        $this->successfulConnectionMock();

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\n\r\nsome post data$#"));

        $request->end('some post data');

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");
    }

    /** @test */
    public function writeWithAPostRequestShouldSendToTheStream()
    {
        $requestData = new RequestData('POST', 'http://www.example.com');
        $request = new ClientRequestStream($this->connector, $requestData);

        $this->successfulConnectionMock();

        $this->stream->expects($this->exactly(3))->method('write')->withConsecutive(
            array($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\n\r\nsome$#")),
            array($this->identicalTo("post")),
            array($this->identicalTo("data"))
        );

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
        $request = new ClientRequestStream($this->connector, $requestData);

        $resolveConnection = $this->successfulAsyncConnectionMock();

        $this->stream->expects($this->exactly(2))->method('write')->withConsecutive(
            array($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\n\r\nsomepost$#")),
            array($this->identicalTo("data"))
        )->willReturn(
            true
        );

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
        $request = new ClientRequestStream($this->connector, $requestData);

        $this->stream = $this->getMockBuilder('React\Socket\Connection')
            ->disableOriginalConstructor()
            ->setMethods(array('write'))
            ->getMock();

        $resolveConnection = $this->successfulAsyncConnectionMock();

        $this->stream->expects($this->exactly(2))->method('write')->withConsecutive(
            array($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\n\r\nsomepost$#")),
            array($this->identicalTo("data"))
        )->willReturn(
            false
        );

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
        $request = new ClientRequestStream($this->connector, $requestData);

        $this->successfulConnectionMock();

        $this->stream->expects($this->exactly(3))->method('write')->withConsecutive(
            array($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\n\r\nsome$#")),
            array($this->identicalTo("post")),
            array($this->identicalTo("data"))
        );

        $loop = $this
            ->getMockBuilder('React\EventLoop\LoopInterface')
            ->getMock();

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
        $request = new ClientRequestStream($this->connector, $requestData);

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
        $request = new ClientRequestStream($this->connector, $requestData);

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
        $request = new ClientRequestStream($this->connector, $requestData);

        $request->on('close', $this->expectCallableOnce());
        $request->close();
    }

    /**
     * @test
     */
    public function writeAfterCloseReturnsFalse()
    {
        $requestData = new RequestData('POST', 'http://www.example.com');
        $request = new ClientRequestStream($this->connector, $requestData);

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
        $request = new ClientRequestStream($this->connector, $requestData);

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
        $request = new ClientRequestStream($this->connector, $requestData);

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
    public function requestShouldRemoveAllListenerAfterClosed()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new ClientRequestStream($this->connector, $requestData);

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

    /** @test */
    public function multivalueHeader()
    {
        $requestData = new RequestData('GET', 'http://www.example.com');
        $request = new ClientRequestStream($this->connector, $requestData);

        $this->successfulConnectionMock();

        $response = null;
        $request->on('response', $this->expectCallableOnce());
        $request->on('response', function ($value) use (&$response) {
            $response = $value;
        });

        $request->end();

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("X-Xss-Protection:1; mode=block\r\n");
        $request->handleData("Cache-Control:public, must-revalidate, max-age=0\r\n");
        $request->handleData("\r\nbody");

        /** @var \Psr\Http\Message\ResponseInterface $response */
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
        $this->assertEquals('1.0', $response->getProtocolVersion());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('1; mode=block', $response->getHeaderLine('X-Xss-Protection'));
        $this->assertEquals('public, must-revalidate, max-age=0', $response->getHeaderLine('Cache-Control'));
    }
}
