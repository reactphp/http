<?php

namespace React\Tests\Http\Io;

use Psr\Http\Message\ResponseInterface;
use React\Http\Io\ClientRequestStream;
use React\Http\Message\Request;
use React\Http\Message\Uri;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Stream\DuplexResourceStream;
use React\Stream\ReadableStreamInterface;
use React\Tests\Http\TestCase;

class ClientRequestStreamTest extends TestCase
{
    /** @test */
    public function testRequestShouldUseConnectionManagerWithUriFromRequestAndBindToStreamEvents()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();

        $uri = new Uri('http://www.example.com');
        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->with($uri)->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', $uri);
        $request = new ClientRequestStream($connectionManager, $requestData);

        $connection->expects($this->atLeast(5))->method('on')->withConsecutive(
            array('drain', $this->identicalTo(array($request, 'handleDrain'))),
            array('data', $this->identicalTo(array($request, 'handleData'))),
            array('end', $this->identicalTo(array($request, 'handleEnd'))),
            array('error', $this->identicalTo(array($request, 'handleError'))),
            array('close', $this->identicalTo(array($request, 'close')))
        );

        $connection->expects($this->exactly(5))->method('removeListener')->withConsecutive(
            array('drain', $this->identicalTo(array($request, 'handleDrain'))),
            array('data', $this->identicalTo(array($request, 'handleData'))),
            array('end', $this->identicalTo(array($request, 'handleEnd'))),
            array('error', $this->identicalTo(array($request, 'handleError'))),
            array('close', $this->identicalTo(array($request, 'close')))
        );

        $request->end();

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");
    }

    /** @test */
    public function requestShouldEmitErrorIfConnectionFails()
    {
        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\reject(new \RuntimeException()));

        $requestData = new Request('GET', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('error', $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));
        $request->on('close', $this->expectCallableOnce());

        $request->end();
    }

    /** @test */
    public function requestShouldEmitErrorIfConnectionClosesBeforeResponseIsParsed()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('error', $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));
        $request->on('close', $this->expectCallableOnce());

        $request->end();
        $request->handleEnd();
    }

    /** @test */
    public function requestShouldEmitErrorIfConnectionEmitsError()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('error', $this->expectCallableOnceWith($this->isInstanceOf('Exception')));
        $request->on('close', $this->expectCallableOnce());

        $request->end();
        $request->handleError(new \Exception('test'));
    }

    /** @test */
    public function requestShouldEmitErrorIfRequestParserThrowsException()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('error', $this->expectCallableOnceWith($this->isInstanceOf('InvalidArgumentException')));
        $request->on('close', $this->expectCallableOnce());

        $request->end();
        $request->handleData("\r\n\r\n");
    }

    /** @test */
    public function getRequestShouldSendAGetRequest()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.0\r\nHost: www.example.com\r\n\r\n");

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array(), '', '1.0');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->end();
    }

    /** @test */
    public function getHttp11RequestShouldSendAGetRequestWithGivenConnectionCloseHeader()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array('Connection' => 'close'), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->end();
    }

    /** @test */
    public function getOptionsAsteriskShouldSendAOptionsRequestAsteriskRequestTarget()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("OPTIONS * HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('OPTIONS', 'http://www.example.com', array('Connection' => 'close'), '', '1.1');
        $requestData = $requestData->withRequestTarget('*');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->end();
    }

    public function testStreamShouldEmitResponseWithEmptyBodyWhenResponseContainsContentLengthZero()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array('Connection' => 'close'), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $that = $this;
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($that) {
            $body->on('data', $that->expectCallableNever());
            $body->on('end', $that->expectCallableOnce());
            $body->on('close', $that->expectCallableOnce());
        });
        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n");
    }

    public function testStreamShouldEmitResponseWithEmptyBodyWhenResponseContainsStatusNoContent()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array('Connection' => 'close'), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $that = $this;
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($that) {
            $body->on('data', $that->expectCallableNever());
            $body->on('end', $that->expectCallableOnce());
            $body->on('close', $that->expectCallableOnce());
        });
        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 204 No Content\r\n\r\n");
    }

    public function testStreamShouldEmitResponseWithEmptyBodyWhenResponseContainsStatusNotModifiedWithContentLengthGiven()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array('Connection' => 'close'), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $that = $this;
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($that) {
            $body->on('data', $that->expectCallableNever());
            $body->on('end', $that->expectCallableOnce());
            $body->on('close', $that->expectCallableOnce());
        });
        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 304 Not Modified\r\nContent-Length: 100\r\n\r\n");
    }

    public function testStreamShouldEmitResponseWithEmptyBodyWhenRequestMethodIsHead()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("HEAD / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('HEAD', 'http://www.example.com', array('Connection' => 'close'), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $that = $this;
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($that) {
            $body->on('data', $that->expectCallableNever());
            $body->on('end', $that->expectCallableOnce());
            $body->on('close', $that->expectCallableOnce());
        });
        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 100\r\n\r\n");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyUntilEndWhenResponseContainsContentLengthAndResponseBody()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array('Connection' => 'close'), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $that = $this;
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($that) {
            $body->on('data', $that->expectCallableOnceWith('OK'));
            $body->on('end', $that->expectCallableOnce());
            $body->on('close', $that->expectCallableOnce());
        });
        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nOK");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyWithoutDataWhenResponseContainsContentLengthWithoutResponseBody()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array('Connection' => 'close'), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $that = $this;
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($that) {
            $body->on('data', $that->expectCallableNever());
            $body->on('end', $that->expectCallableNever());
            $body->on('close', $that->expectCallableNever());
        });
        $request->on('close', $this->expectCallableNever());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\n");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyWithDataWithoutEndWhenResponseContainsContentLengthWithIncompleteResponseBody()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array('Connection' => 'close'), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $that = $this;
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($that) {
            $body->on('data', $that->expectCallableOnce('O'));
            $body->on('end', $that->expectCallableNever());
            $body->on('close', $that->expectCallableNever());
        });
        $request->on('close', $this->expectCallableNever());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nO");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyUntilEndWhenResponseContainsTransferEncodingChunkedAndResponseBody()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array('Connection' => 'close'), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $that = $this;
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($that) {
            $body->on('data', $that->expectCallableOnceWith('OK'));
            $body->on('end', $that->expectCallableOnce());
            $body->on('close', $that->expectCallableOnce());
        });
        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n2\r\nOK\r\n0\r\n\r\n");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyWithoutDataWhenResponseContainsTransferEncodingChunkedWithoutResponseBody()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array('Connection' => 'close'), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $that = $this;
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($that) {
            $body->on('data', $that->expectCallableNever());
            $body->on('end', $that->expectCallableNever());
            $body->on('close', $that->expectCallableNever());
        });
        $request->on('close', $this->expectCallableNever());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyWithDataWithoutEndWhenResponseContainsTransferEncodingChunkedWithIncompleteResponseBody()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array('Connection' => 'close'), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $that = $this;
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($that) {
            $body->on('data', $that->expectCallableOnceWith('O'));
            $body->on('end', $that->expectCallableNever());
            $body->on('close', $that->expectCallableNever());
        });
        $request->on('close', $this->expectCallableNever());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n2\r\nO");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyWithDataWithoutEndWhenResponseContainsNoContentLengthAndIncompleteResponseBody()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: close\r\n\r\n");
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array('Connection' => 'close'), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $that = $this;
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($that) {
            $body->on('data', $that->expectCallableOnce('O'));
            $body->on('end', $that->expectCallableNever());
            $body->on('close', $that->expectCallableNever());
        });
        $request->on('close', $this->expectCallableNever());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\n\r\nO");
    }

    public function testStreamShouldEmitResponseWithStreamingBodyUntilEndWhenResponseContainsNoContentLengthAndResponseBodyTerminatedByConnectionEndEvent()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
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

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array('Connection' => 'close'), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $that = $this;
        $request->on('response', function (ResponseInterface $response, ReadableStreamInterface $body) use ($that) {
            $body->on('data', $that->expectCallableOnce('OK'));
            $body->on('end', $that->expectCallableOnce());
            $body->on('close', $that->expectCallableOnce());
        });
        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\n\r\nOK");

        $this->assertNotNull($endEvent);
        call_user_func($endEvent); // $endEvent() (PHP 5.4+)
    }

    public function testStreamShouldReuseConnectionForHttp11ByDefault()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\n\r\n");
        $connection->expects($this->once())->method('isReadable')->willReturn(true);
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));
        $connectionManager->expects($this->once())->method('keepAlive')->with(new Uri('http://www.example.com'), $connection);

        $requestData = new Request('GET', 'http://www.example.com', array(), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n");
    }

    public function testStreamShouldNotReuseConnectionWhenResponseContainsConnectionClose()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\n\r\n");
        $connection->expects($this->once())->method('isReadable')->willReturn(true);
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array(), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
    }

    public function testStreamShouldNotReuseConnectionWhenRequestContainsConnectionCloseWithAdditionalOptions()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\nConnection: FOO, CLOSE, BAR\r\n\r\n");
        $connection->expects($this->once())->method('isReadable')->willReturn(true);
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array('Connection' => 'FOO, CLOSE, BAR'), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 0\r\nConnection: Foo, Close, Bar\r\n\r\n");
    }

    public function testStreamShouldNotReuseConnectionForHttp10ByDefault()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.0\r\nHost: www.example.com\r\n\r\n");
        $connection->expects($this->once())->method('isReadable')->willReturn(true);
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array(), '', '1.0');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.0 200 OK\r\nContent-Length: 0\r\n\r\n");
    }

    public function testStreamShouldReuseConnectionForHttp10WhenBothRequestAndResponseContainConnectionKeepAlive()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.0\r\nHost: www.example.com\r\nConnection: keep-alive\r\n\r\n");
        $connection->expects($this->once())->method('isReadable')->willReturn(true);
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));
        $connectionManager->expects($this->once())->method('keepAlive')->with(new Uri('http://www.example.com'), $connection);

        $requestData = new Request('GET', 'http://www.example.com', array('Connection' => 'keep-alive'), '', '1.0');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.0 200 OK\r\nContent-Length: 0\r\nConnection: keep-alive\r\n\r\n");
    }

    public function testStreamShouldReuseConnectionForHttp10WhenBothRequestAndResponseContainConnectionKeepAliveWithAdditionalOptions()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.0\r\nHost: www.example.com\r\nConnection: FOO, KEEP-ALIVE, BAR\r\n\r\n");
        $connection->expects($this->once())->method('isReadable')->willReturn(true);
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));
        $connectionManager->expects($this->once())->method('keepAlive')->with(new Uri('http://www.example.com'), $connection);

        $requestData = new Request('GET', 'http://www.example.com', array('Connection' => 'FOO, KEEP-ALIVE, BAR'), '', '1.0');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.0 200 OK\r\nContent-Length: 0\r\nConnection: Foo, Keep-Alive, Bar\r\n\r\n");
    }

    public function testStreamShouldNotReuseConnectionWhenResponseContainsNoContentLengthAndResponseBodyTerminatedByConnectionEndEvent()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
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

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array(), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\n\r\n");

        $this->assertNotNull($endEvent);
        call_user_func($endEvent); // $endEvent() (PHP 5.4+)
    }

    public function testStreamShouldNotReuseConnectionWhenResponseContainsContentLengthButIsTerminatedByUnexpectedCloseEvent()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
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

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array(), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\n");

        $this->assertNotNull($closeEvent);
        call_user_func($closeEvent); // $closeEvent() (PHP 5.4+)
    }

    public function testStreamShouldReuseConnectionWhenResponseContainsTransferEncodingChunkedAndResponseBody()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\n\r\n");
        $connection->expects($this->once())->method('isReadable')->willReturn(true);
        $connection->expects($this->never())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));
        $connectionManager->expects($this->once())->method('keepAlive')->with(new Uri('http://www.example.com'), $connection);

        $requestData = new Request('GET', 'http://www.example.com', array(), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n2\r\nOK\r\n0\r\n\r\n");
    }

    public function testStreamShouldNotReuseConnectionWhenResponseContainsTransferEncodingChunkedAndResponseBodyContainsInvalidData()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with("GET / HTTP/1.1\r\nHost: www.example.com\r\n\r\n");
        $connection->expects($this->atMost(1))->method('isReadable')->willReturn(true);
        $connection->expects($this->once())->method('close');

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('GET', 'http://www.example.com', array(), '', '1.1');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->on('close', $this->expectCallableOnce());

        $request->end();

        $request->handleData("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\nINVALID\r\n");
    }

    /** @test */
    public function postRequestShouldSendAPostRequest()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('write')->with($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\n\r\nsome post data$#"));

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('POST', 'http://www.example.com', array(), '', '1.0');
        $request = new ClientRequestStream($connectionManager, $requestData);

        $request->end('some post data');

        $request->handleData("HTTP/1.0 200 OK\r\n");
        $request->handleData("Content-Type: text/plain\r\n");
        $request->handleData("\r\nbody");
    }

    /** @test */
    public function writeWithAPostRequestShouldSendToTheStream()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->exactly(3))->method('write')->withConsecutive(
            array($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\n\r\nsome$#")),
            array($this->identicalTo("post")),
            array($this->identicalTo("data"))
        );

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('POST', 'http://www.example.com', array(), '', '1.0');
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
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->exactly(2))->method('write')->withConsecutive(
            array($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\n\r\nsomepost$#")),
            array($this->identicalTo("data"))
        )->willReturn(
            true
        );

        $deferred = new Deferred();
        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn($deferred->promise());

        $requestData = new Request('POST', 'http://www.example.com', array(), '', '1.0');
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
        $connection = $this->getMockBuilder('React\Socket\Connection')
            ->disableOriginalConstructor()
            ->setMethods(array('write'))
            ->getMock();

        $connection->expects($this->exactly(2))->method('write')->withConsecutive(
            array($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\n\r\nsomepost$#")),
            array($this->identicalTo("data"))
        )->willReturn(
            false
        );

        $deferred = new Deferred();
        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn($deferred->promise());

        $requestData = new Request('POST', 'http://www.example.com', array(), '', '1.0');
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
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->exactly(3))->method('write')->withConsecutive(
            array($this->matchesRegularExpression("#^POST / HTTP/1\.0\r\nHost: www.example.com\r\n\r\nsome$#")),
            array($this->identicalTo("post")),
            array($this->identicalTo("data"))
        );

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

        $requestData = new Request('POST', 'http://www.example.com', array(), '', '1.0');
        $request = new ClientRequestStream($connectionManager, $requestData);

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
        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
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
        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
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
        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();

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
        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();

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
        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
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
        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
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
        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();

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
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();

        $connectionManager = $this->getMockBuilder('React\Http\Io\ClientConnectionManager')->disableOriginalConstructor()->getMock();
        $connectionManager->expects($this->once())->method('connect')->willReturn(\React\Promise\resolve($connection));

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
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
        $this->assertEquals('1.0', $response->getProtocolVersion());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('1; mode=block', $response->getHeaderLine('X-Xss-Protection'));
        $this->assertEquals('public, must-revalidate, max-age=0', $response->getHeaderLine('Cache-Control'));
    }
}
