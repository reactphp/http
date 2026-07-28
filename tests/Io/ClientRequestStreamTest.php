<?php

namespace React\Tests\Http\Io;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Http\Io\ClientConnectionManager;
use React\Http\Io\ClientRequestStream;
use React\Http\Message\Request;
use React\Http\Message\Uri;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Socket\Connection;
use React\Socket\ConnectionInterface;
use React\Stream\DuplexResourceStream;
use React\Stream\ReadableStreamInterface;
use React\Tests\Http\TestCase;
use function React\Promise\reject;
use function React\Promise\resolve;

class ClientRequestStreamTest extends TestCase
{
    /** @test */
    public function testRequestShouldUseConnectionManagerWithUriFromRequestAndBindToStreamEvents()
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $uri = new Uri('http://www.example.com');
        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->with($uri)->willReturn(resolve($connection));

        $requestData = new Request('GET', $uri);
        $request = new ClientRequestStream($connectionManager, $requestData);

        $connection->expects($this->atLeast(5))->method('on')->withConsecutive(
            ['drain', $this->identicalTo([$request, 'handleDrain'])],
            ['data', $this->identicalTo([$request, 'handleData'])],
            ['end', $this->identicalTo([$request, 'handleEnd'])],
            ['error', $this->identicalTo([$request, 'handleError'])],
            ['close', $this->identicalTo([$request, 'close'])]
        );

        $connection->expects($this->exactly(5))->method('removeListener')->withConsecutive(
            ['drain', $this->identicalTo([$request, 'handleDrain'])],
            ['data', $this->identicalTo([$request, 'handleData'])],
            ['end', $this->identicalTo([$request, 'handleEnd'])],
            ['error', $this->identicalTo([$request, 'handleError'])],
            ['close', $this->identicalTo([$request, 'close'])]
        );

        $request->end();

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");
    }

    /** @test */
    public function requestShouldEmitErrorIfConnectionFails()
    {
        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(reject(new \RuntimeException()));

        $requestData = new Request('GET', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('error', $this->expectCallableOnceWith($this->isInstanceOf(\RuntimeException::class)));
        $request->on('close', $this->expectCallableOnce());

        $request->end();
    }

    /** @test */
    public function requestShouldEmitErrorIfConnectionClosesBeforeResponseIsParsed()
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('error', $this->expectCallableOnceWith($this->isInstanceOf(\RuntimeException::class)));
        $request->on('close', $this->expectCallableOnce());

        $request->end();
        $request->handleEnd();
    }

    /** @test */
    public function requestShouldEmitErrorIfConnectionEmitsError()
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('error', $this->expectCallableOnceWith($this->isInstanceOf(\Exception::class)));
        $request->on('close', $this->expectCallableOnce());

        $request->end();
        $request->handleError(new \Exception('test'));
    }

    public static function provideInvalidRequest()
    {
        $request = new Request('GET' , "http://localhost/");

        yield [
            $request->withMethod("INVA\r\nLID", '')
        ];
        yield [
            $request->withRequestTarget('/inva lid')
        ];
        yield [
            $request->withHeader('Invalid', "Yes\r\n")
        ];
        yield [
            $request->withHeader('Invalid', "Yes\n")
        ];
        yield [
            $request->withHeader('Invalid', "Yes\r")
        ];
        yield [
            $request->withHeader("Inva\r\nlid", 'Yes')
        ];
        yield [
            $request->withHeader("Inva\nlid", 'Yes')
        ];
        yield [
            $request->withHeader("Inva\rlid", 'Yes')
        ];
        yield [
            $request->withHeader('Inva Lid', 'Yes')
        ];
        yield [
            $request->withHeader('Inva:Lid', 'Yes')
        ];
        yield [
            $request->withHeader('Invalid', "Val\0ue")
        ];
        yield [
            $request->withHeader("Inva\0lid", 'Yes')
        ];
    }

    /**
     * @dataProvider provideInvalidRequest
     * @param RequestInterface $request
     */
    public function testStreamShouldEmitErrorBeforeCreatingConnectionWhenRequestIsInvalid(RequestInterface $request)
    {
        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->never())->method('connect');

        $stream = new ClientRequestStream($connectionManager, $request);

        $stream->on('error', $this->expectCallableOnceWith($this->isInstanceOf(\InvalidArgumentException::class)));
        $stream->on('close', $this->expectCallableOnce());

        $stream->end();
    }

    /** @test */
    public function requestShouldEmitErrorIfRequestParserThrowsException()
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('error', $this->expectCallableOnceWith($this->isInstanceOf(\InvalidArgumentException::class)));
        $request->on('close', $this->expectCallableOnce());

        $request->end();
        $request->handleData("\r\n\r\n");
    }

    public static function provideResponseHeaderOverflow()
    {
        $data = "HTTP/1.1 200 OK\r\nContent-Length: 0\r\nX-Data: ";
        $data .= str_repeat('A', 65537 - strlen($data)) . "\r\n\r\n";

        $legacy = "HTTP/1.1 200 OK\r\n";
        $legacy .= str_repeat('A', 65537 - strlen($legacy)) . "\n\n";

        return [
            'without end of message' => [str_repeat('A', 65537)],
            'with end of message' => [$data],
            'with legacy end of message' => [$legacy],
        ];
    }

    /**
     * @dataProvider provideResponseHeaderOverflow
     * @param string $data
     */
    public function testRequestShouldEmitErrorWhenResponseHeadersExceedMaximumSize($data)
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('response', $this->expectCallableNever());
        $request->on('error', $this->expectCallableOnceWith($this->isInstanceOf('OverflowException')));
        $request->on('close', $this->expectCallableOnce());

        $request->end();
        $request->handleData($data);
    }

    /** @test */
    public function getRequestShouldSendAGetRequest()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.0\r\nHost: www.example.com\r\n\r\n");

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', [], '', '1.0');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->end();
    }

    /** @test */
    public function getHttp11RequestShouldSendAGetRequestWithGivenConnectionCloseHeader()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', ['Connection' => 'close'], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->end();
    }

    /** @test */
    public function getOptionsAsteriskShouldSendAOptionsRequestAsteriskRequestTarget()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("OPTIONS * HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('OPTIONS', 'http://www.example.com', ['Connection' => 'close'], '', '1.1');
        $requestData = $requestData->withRequestTarget('*');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->end();
    }

    public function testStreamShouldEmitResponseWithEmptyBodyWhenResponseContainsContentLengthZero()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', ['Connection' => 'close'], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) {
            $body->on('data', $this->expectCallableNever());
            $body->on('end', $this->expectCallableOnce());
            $body->on('close', $this->expectCallableOnce());
        });
        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n");
    }

    public function testStreamShouldEmitResponseWithEmptyBodyWhenResponseContainsStatusNoContent()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', ['Connection' => 'close'], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) {
            $body->on('data', $this->expectCallableNever());
            $body->on('end', $this->expectCallableOnce());
            $body->on('close', $this->expectCallableOnce());
        });
        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 204 No Content\r\n\r\n");
    }

    public function testStreamShouldEmitResponseWithEmptyBodyWhenResponseContainsStatusNotModifiedWithContentLengthGiven()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', ['Connection' => 'close'], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) {
            $body->on('data', $this->expectCallableNever());
            $body->on('end', $this->expectCallableOnce());
            $body->on('close', $this->expectCallableOnce());
        });
        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 304 Not Modified\r\nContent-Length: 100\r\n\r\n");
    }

    public function testStreamShouldEmitResponseWithEmptyBodyWhenRequestMethodIsHead()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("HEAD / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('HEAD', 'http://www.example.com', ['Connection' => 'close'], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) {
            $body->on('data', $this->expectCallableNever());
            $body->on('end', $this->expectCallableOnce());
            $body->on('close', $this->expectCallableOnce());
        });
        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 100\r\n\r\n");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyUntilEndWhenResponseContainsContentLengthAndResponseBody()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', ['Connection' => 'close'], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) {
            $body->on('data', $this->expectCallableOnceWith('OK'));
            $body->on('end', $this->expectCallableOnce());
            $body->on('close', $this->expectCallableOnce());
        });
        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nOK");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyWithoutDataWhenResponseContainsContentLengthWithoutResponseBody()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', ['Connection' => 'close'], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) {
            $body->on('data', $this->expectCallableNever());
            $body->on('end', $this->expectCallableNever());
            $body->on('close', $this->expectCallableNever());
        });
        $request->on('close', $this->expectCallableNever());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\n");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyWithDataWithoutEndWhenResponseContainsContentLengthWithIncompleteResponseBody()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', ['Connection' => 'close'], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) {
            $body->on('data', $this->expectCallableOnce('O'));
            $body->on('end', $this->expectCallableNever());
            $body->on('close', $this->expectCallableNever());
        });
        $request->on('close', $this->expectCallableNever());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nO");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyUntilEndWhenResponseContainsTransferEncodingChunkedAndResponseBody()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', ['Connection' => 'close'], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) {
            $body->on('data', $this->expectCallableOnceWith('OK'));
            $body->on('end', $this->expectCallableOnce());
            $body->on('close', $this->expectCallableOnce());
        });
        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n2\r\nOK\r\n0\r\n\r\n");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyWithoutDataWhenResponseContainsTransferEncodingChunkedWithoutResponseBody()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', ['Connection' => 'close'], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) {
            $body->on('data', $this->expectCallableNever());
            $body->on('end', $this->expectCallableNever());
            $body->on('close', $this->expectCallableNever());
        });
        $request->on('close', $this->expectCallableNever());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyWithDataWithoutEndWhenResponseContainsTransferEncodingChunkedWithIncompleteResponseBody()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', ['Connection' => 'close'], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) {
            $body->on('data', $this->expectCallableOnceWith('O'));
            $body->on('end', $this->expectCallableNever());
            $body->on('close', $this->expectCallableNever());
        });
        $request->on('close', $this->expectCallableNever());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n2\r\nO");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyWithDataWithoutEndWhenResponseContainsNoContentLengthAndIncompleteResponseBody()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', ['Connection' => 'close'], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) {
            $body->on('data', $this->expectCallableOnce('O'));
            $body->on('end', $this->expectCallableNever());
            $body->on('close', $this->expectCallableNever());
        });
        $request->on('close', $this->expectCallableNever());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\n\r\nO");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyUntilEndWhenResponseContainsNoContentLengthAndResponseBodyTerminatedByConnectionEndEvent()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->once())->method('close');

        $endEvent = null;
        $eventName = null;
        $connection->expects($this->any())->method('on')->with($this->callback(function ($name) use (&$eventName) {
            $eventName = $name;
            return true;
        }), $this->callback(function ($cb) use (&$endEvent, &$eventName) {
            if ($eventName === 'end') {
                $endEvent = $cb;
            }
            return true;
        }));

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', ['Connection' => 'close'], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) {
            $body->on('data', $this->expectCallableOnce('OK'));
            $body->on('end', $this->expectCallableOnce());
            $body->on('close', $this->expectCallableOnce());
        });
        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\n\r\nOK");

        $this->assertNotNull($endEvent);
        call_user_func($endEvent); // $endEvent() (PHP 5.4+)
    }

    public function testStreamShouldReuseConnectionForHttp11ByDefault()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\n\r\n");
        $connection->expects($this->once())->method('isReadable')->willReturn(true);
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));
        $connectionManager->expects($this->once())->method('keepAlive')->with(new Uri('http://www.example.com'), $connection);

        $requestData = new Request('GET', 'http://www.example.com', [], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n");
    }

    public function testStreamShouldNotReuseConnectionWhenResponseContainsConnectionClose()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\n\r\n");
        $connection->expects($this->once())->method('isReadable')->willReturn(true);
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', [], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
    }

    public function testStreamShouldNotReuseConnectionWhenRequestContainsConnectionCloseWithAdditionalOptions()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: FOO, CLOSE, BAR\r\n\r\n");
        $connection->expects($this->once())->method('isReadable')->willReturn(true);
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', ['Connection' => 'FOO, CLOSE, BAR'], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 0\r\nConnection: Foo, Close, Bar\r\n\r\n");
    }

    public function testStreamShouldNotReuseConnectionForHttp10ByDefault()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.0\r\nHost: www.example.com\r\n\r\n");
        $connection->expects($this->once())->method('isReadable')->willReturn(true);
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', [], '', '1.0');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.0 200 OK\r\nContent-Length: 0\r\n\r\n");
    }

    public function testStreamShouldReuseConnectionForHttp10WhenBothRequestAndResponseContainConnectionKeepAlive()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.0\r\nHost: www.example.com\r\nConnection: keep-alive\r\n\r\n");
        $connection->expects($this->once())->method('isReadable')->willReturn(true);
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));
        $connectionManager->expects($this->once())->method('keepAlive')->with(new Uri('http://www.example.com'), $connection);

        $requestData = new Request('GET', 'http://www.example.com', ['Connection' => 'keep-alive'], '', '1.0');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.0 200 OK\r\nContent-Length: 0\r\nConnection: keep-alive\r\n\r\n");
    }

    public function testStreamShouldReuseConnectionForHttp10WhenBothRequestAndResponseContainConnectionKeepAliveWithAdditionalOptions()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.0\r\nHost: www.example.com\r\nConnection: FOO, KEEP-ALIVE, BAR\r\n\r\n");
        $connection->expects($this->once())->method('isReadable')->willReturn(true);
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));
        $connectionManager->expects($this->once())->method('keepAlive')->with(new Uri('http://www.example.com'), $connection);

        $requestData = new Request('GET', 'http://www.example.com', ['Connection' => 'FOO, KEEP-ALIVE, BAR'], '', '1.0');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.0 200 OK\r\nContent-Length: 0\r\nConnection: Foo, Keep-Alive, Bar\r\n\r\n");
    }

    public function testStreamShouldNotReuseConnectionWhenResponseContainsNoContentLengthAndResponseBodyTerminatedByConnectionEndEvent()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\n\r\n");
        $connection->expects($this->once())->method('isReadable')->willReturn(false);
        $connection->expects($this->once())->method('close');

        $endEvent = null;
        $eventName = null;
        $connection->expects($this->any())->method('on')->with($this->callback(function ($name) use (&$eventName) {
            $eventName = $name;
            return true;
        }), $this->callback(function ($cb) use (&$endEvent, &$eventName) {
            if ($eventName === 'end') {
                $endEvent = $cb;
            }
            return true;
        }));

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', [], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\n\r\n");

        $this->assertNotNull($endEvent);
        call_user_func($endEvent); // $endEvent() (PHP 5.4+)
    }

    public function testStreamShouldNotReuseConnectionWhenResponseContainsContentLengthButIsTerminatedByUnexpectedCloseEvent()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\n\r\n");
        $connection->expects($this->atMost(1))->method('isReadable')->willReturn(false);
        $connection->expects($this->once())->method('close');

        $closeEvent = null;
        $eventName = null;
        $connection->expects($this->any())->method('on')->with($this->callback(function ($name) use (&$eventName) {
            $eventName = $name;
            return true;
        }), $this->callback(function ($cb) use (&$closeEvent, &$eventName) {
            if ($eventName === 'close') {
                $closeEvent = $cb;
            }
            return true;
        }));

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', [], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\n");

        $this->assertNotNull($closeEvent);
        call_user_func($closeEvent); // $closeEvent() (PHP 5.4+)
    }

    public function testStreamShouldReuseConnectionWhenResponseContainsTransferEncodingChunkedAndResponseBody()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\n\r\n");
        $connection->expects($this->once())->method('isReadable')->willReturn(true);
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));
        $connectionManager->expects($this->once())->method('keepAlive')->with(new Uri('http://www.example.com'), $connection);

        $requestData = new Request('GET', 'http://www.example.com', [], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n2\r\nOK\r\n0\r\n\r\n");
    }

    public function testStreamShouldNotReuseConnectionWhenResponseContainsTransferEncodingChunkedAndResponseBodyContainsInvalidData()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\n\r\n");
        $connection->expects($this->atMost(1))->method('isReadable')->willReturn(true);
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', [], '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\nINVALID\r\n");
    }

    /** @test */
    public function postRequestShouldSendAPostRequest()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('write')->with($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\n\r\nsome post data$#"));

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('POST', 'http://www.example.com', [], '', '1.0');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->end('some post data');

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");
    }

    /** @test */
    public function writeWithAPostRequestShouldSendToTheStream()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->exactly(3))->method('write')->withConsecutive(
            [$this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\n\r\nsome$#")],
            [$this->identicalTo("post")],
            [$this->identicalTo("data")]
        );

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('POST', 'http://www.example.com', [], '', '1.0');
        $request = new ClientRequestStream($connectionManager, $requestData);

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
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->exactly(2))->method('write')->withConsecutive(
            [$this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\n\r\nsomepost$#")],
            [$this->identicalTo("data")]
        )->willReturn(
            true
        );

        $deferred = new Deferred();
        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn($deferred->promise());

        $requestData = new Request('POST', 'http://www.example.com', [], '', '1.0');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $this->assertFalse($request->write("some"));
        $this->assertFalse($request->write("post"));

        $request->on('drain', $this->expectCallableOnce());
        $request->once('drain', function () use ($request) {
            $request->write("data");
            $request->end();
        });

        $deferred->resolve($connection);

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");
    }

    /** @test */
    public function writeWithAPostRequestShouldForwardDrainEventIfFirstChunkExceedsBuffer()
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->setMethods(['write'])
            ->getMock();

        $connection->expects($this->exactly(2))->method('write')->withConsecutive(
            [$this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\n\r\nsomepost$#")],
            [$this->identicalTo("data")]
        )->willReturn(
            false
        );

        $deferred = new Deferred();
        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn($deferred->promise());

        $requestData = new Request('POST', 'http://www.example.com', [], '', '1.0');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $this->assertFalse($request->write("some"));
        $this->assertFalse($request->write("post"));

        $request->on('drain', $this->expectCallableOnce());
        $request->once('drain', function () use ($request) {
            $request->write("data");
            $request->end();
        });

        $deferred->resolve($connection);
        $connection->emit('drain');

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");
    }

    /** @test */
    public function pipeShouldPipeDataIntoTheRequestBody()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->exactly(3))->method('write')->withConsecutive(
            [$this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\n\r\nsome$#")],
            [$this->identicalTo("post")],
            [$this->identicalTo("data")]
        );

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('POST', 'http://www.example.com', [], '', '1.0');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $loop = $this->createMock(LoopInterface::class);

        $stream = fopen('php://memory', 'r+');
        $stream = new DuplexResourceStream($stream, $loop);

        $stream->pipe($request);
        $stream->emit('data', ['some']);
        $stream->emit('data', ['post']);
        $stream->emit('data', ['data']);

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");
    }

    /**
     * @test
     */
    public function writeShouldStartConnecting()
    {
        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(new Promise(function () { }));

        $requestData = new Request('POST', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->write('test');
    }

    /**
     * @test
     */
    public function endShouldStartConnectingAndChangeStreamIntoNonWritableMode()
    {
        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(new Promise(function () { }));

        $requestData = new Request('POST', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->end();

        $this->assertFalse($request->isWritable());
    }

    /**
     * @test
     */
    public function closeShouldEmitCloseEvent()
    {
        $connectionManager = $this->createMock(ClientConnectionManager::class);

        $requestData = new Request('POST', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());
        $request->close();
    }

    /**
     * @test
     */
    public function writeAfterCloseReturnsFalse()
    {
        $connectionManager = $this->createMock(ClientConnectionManager::class);

        $requestData = new Request('POST', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->close();

        $this->assertFalse($request->isWritable());
        $this->assertFalse($request->write('nope'));
    }

    /**
     * @test
     */
    public function endAfterCloseIsNoOp()
    {
        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->never())->method('connect');

        $requestData = new Request('POST', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->close();
        $request->end();
    }

    /**
     * @test
     */
    public function closeShouldCancelPendingConnectionAttempt()
    {
        $promise = new Promise(function () {}, function () {
            throw new \RuntimeException();
        });
        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn($promise);

        $requestData = new Request('POST', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->end();

        $request->on('error', $this->expectCallableNever());
        $request->on('close', $this->expectCallableOnce());

        $request->close();
        $request->close();
    }

    /** @test */
    public function requestShouldRemoveAllListenerAfterClosed()
    {
        $connectionManager = $this->createMock(ClientConnectionManager::class);

        $requestData = new Request('GET', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', function () {});
        $this->assertCount(1, $request->listeners('close'));

        $request->close();
        $this->assertCount(0, $request->listeners('close'));
    }

    /** @test */
    public function multivalueHeader()
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $connectionManager = $this->createMock(ClientConnectionManager::class);
        $connectionManager->expects($this->once())->method('connect')->willReturn(resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

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
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('1.0', $response->getProtocolVersion());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('1; mode=block', $response->getHeaderLine('X-Xss-Protection'));
        $this->assertEquals('public, must-revalidate, max-age=0', $response->getHeaderLine('Cache-Control'));
    }
}
