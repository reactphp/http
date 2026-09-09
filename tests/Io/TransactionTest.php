<?php

namespace React\Tests\Http\Io;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Http\Io\ReadableBodyStream;
use React\Http\Io\Sender;
use React\Http\Io\Transaction;
use React\Http\Message\Request;
use React\Http\Message\Response;
use React\Http\Message\ResponseException;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;
use function React\Async\await;
use function React\Promise\reject;
use function React\Promise\resolve;

class TransactionTest extends TestCase
{
    public function testWithOptionsReturnsNewInstanceWithChangedOption()
    {
        $sender = $this->makeSenderMock();
        $loop = $this->createMock(LoopInterface::class);
        $transaction = new Transaction($sender, $loop);

        $new = $transaction->withOptions(['followRedirects' => false]);

        $this->assertInstanceOf(Transaction::class, $new);
        $this->assertNotSame($transaction, $new);

        $ref = new \ReflectionProperty($new, 'followRedirects');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }

        $this->assertFalse($ref->getValue($new));
    }

    public function testWithOptionsDoesNotChangeOriginalInstance()
    {
        $sender = $this->makeSenderMock();
        $loop = $this->createMock(LoopInterface::class);
        $transaction = new Transaction($sender, $loop);

        $transaction->withOptions(['followRedirects' => false]);

        $ref = new \ReflectionProperty($transaction, 'followRedirects');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }

        $this->assertTrue($ref->getValue($transaction));
    }

    public function testWithOptionsNullValueReturnsNewInstanceWithDefaultOption()
    {
        $sender = $this->makeSenderMock();
        $loop = $this->createMock(LoopInterface::class);
        $transaction = new Transaction($sender, $loop);

        $transaction = $transaction->withOptions(['followRedirects' => false]);
        $transaction = $transaction->withOptions(['followRedirects' => null]);

        $ref = new \ReflectionProperty($transaction, 'followRedirects');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }

        $this->assertTrue($ref->getValue($transaction));
    }

    public function testTimeoutExplicitOptionWillStartTimeoutTimer()
    {
        $timer = $this->createMock(TimerInterface::class);
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())->method('addTimer')->with(2, $this->anything())->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $request = $this->createMock(RequestInterface::class);

        $sender = $this->createMock(Sender::class);
        $sender->expects($this->once())->method('send')->with($request)->willReturn(new Promise(function () { }));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['timeout' => 2]);
        $promise = $transaction->send($request);

        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testTimeoutImplicitFromIniWillStartTimeoutTimer()
    {
        $timer = $this->createMock(TimerInterface::class);
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())->method('addTimer')->with(2, $this->anything())->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $request = $this->createMock(RequestInterface::class);

        $sender = $this->createMock(Sender::class);
        $sender->expects($this->once())->method('send')->with($request)->willReturn(new Promise(function () { }));

        $transaction = new Transaction($sender, $loop);

        $old = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', '2');
        $promise = $transaction->send($request);
        ini_set('default_socket_timeout', $old);

        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testTimeoutExplicitOptionWillRejectWhenTimerFires()
    {
        $timeout = null;
        $timer = $this->createMock(TimerInterface::class);
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())->method('addTimer')->with(2, $this->callback(function ($cb) use (&$timeout) {
            $timeout = $cb;
            return true;
        }))->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $request = $this->createMock(RequestInterface::class);

        $sender = $this->createMock(Sender::class);
        $sender->expects($this->once())->method('send')->with($request)->willReturn(new Promise(function () { }));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['timeout' => 2]);
        $promise = $transaction->send($request);

        $this->assertNotNull($timeout);
        $timeout();

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Request timed out after 2 seconds', $exception->getMessage());
    }

    public function testTimeoutExplicitOptionWillNotStartTimeoutWhenSenderResolvesImmediately()
    {
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->never())->method('addTimer');

        $request = $this->createMock(RequestInterface::class);
        $response = new Response(200, [], '');

        $sender = $this->createMock(Sender::class);
        $sender->expects($this->once())->method('send')->with($request)->willReturn(resolve($response));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['timeout' => 0.001]);
        $promise = $transaction->send($request);

        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $promise->then($this->expectCallableOnceWith($response));
    }

    public function testTimeoutExplicitOptionWillCancelTimeoutTimerWhenSenderResolvesLaterOn()
    {
        $timer = $this->createMock(TimerInterface::class);
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())->method('addTimer')->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $request = $this->createMock(RequestInterface::class);
        $response = new Response(200, [], '');

        $deferred = new Deferred();
        $sender = $this->createMock(Sender::class);
        $sender->expects($this->once())->method('send')->with($request)->willReturn($deferred->promise());

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['timeout' => 0.001]);
        $promise = $transaction->send($request);

        $deferred->resolve($response);

        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $promise->then($this->expectCallableOnceWith($response));
    }

    public function testTimeoutExplicitOptionWillNotStartTimeoutWhenSenderRejectsImmediately()
    {
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->never())->method('addTimer');

        $request = $this->createMock(RequestInterface::class);
        $exception = new \RuntimeException();

        $sender = $this->createMock(Sender::class);
        $sender->expects($this->once())->method('send')->with($request)->willReturn(reject($exception));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['timeout' => 0.001]);
        $promise = $transaction->send($request);

        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $promise->then(null, $this->expectCallableOnceWith($exception));
    }

    public function testTimeoutExplicitOptionWillCancelTimeoutTimerWhenSenderRejectsLaterOn()
    {
        $timer = $this->createMock(TimerInterface::class);
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())->method('addTimer')->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $request = $this->createMock(RequestInterface::class);

        $deferred = new Deferred();
        $sender = $this->createMock(Sender::class);
        $sender->expects($this->once())->method('send')->with($request)->willReturn($deferred->promise());

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['timeout' => 0.001]);
        $promise = $transaction->send($request);

        $exception = new \RuntimeException();
        $deferred->reject($exception);

        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $promise->then(null, $this->expectCallableOnceWith($exception));
    }

    public function testTimeoutExplicitNegativeWillNotStartTimeoutTimer()
    {
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->never())->method('addTimer');

        $request = $this->createMock(RequestInterface::class);

        $sender = $this->createMock(Sender::class);
        $sender->expects($this->once())->method('send')->with($request)->willReturn(new Promise(function () { }));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['timeout' => -1]);
        $promise = $transaction->send($request);

        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testTimeoutExplicitOptionWillNotStartTimeoutTimerWhenRequestBodyIsStreaming()
    {
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->never())->method('addTimer');

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://example.com', [], new ReadableBodyStream($stream));

        $sender = $this->createMock(Sender::class);
        $sender->expects($this->once())->method('send')->with($request)->willReturn(new Promise(function () { }));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['timeout' => 2]);
        $promise = $transaction->send($request);

        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testTimeoutExplicitOptionWillStartTimeoutTimerWhenStreamingRequestBodyIsAlreadyClosed()
    {
        $timer = $this->createMock(TimerInterface::class);
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())->method('addTimer')->with(2, $this->anything())->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $stream = new ThroughStream();
        $stream->close();
        $request = new Request('POST', 'http://example.com', [], new ReadableBodyStream($stream));

        $sender = $this->createMock(Sender::class);
        $sender->expects($this->once())->method('send')->with($request)->willReturn(new Promise(function () { }));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['timeout' => 2]);
        $promise = $transaction->send($request);

        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testTimeoutExplicitOptionWillStartTimeoutTimerWhenStreamingRequestBodyClosesWhileSenderIsStillPending()
    {
        $timer = $this->createMock(TimerInterface::class);
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())->method('addTimer')->with(2, $this->anything())->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://example.com', [], new ReadableBodyStream($stream));

        $sender = $this->createMock(Sender::class);
        $sender->expects($this->once())->method('send')->with($request)->willReturn(new Promise(function () { }));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['timeout' => 2]);
        $promise = $transaction->send($request);

        $stream->close();

        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testTimeoutExplicitOptionWillNotStartTimeoutTimerWhenStreamingRequestBodyClosesAfterSenderRejects()
    {
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->never())->method('addTimer');

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://example.com', [], new ReadableBodyStream($stream));

        $deferred = new Deferred();
        $sender = $this->createMock(Sender::class);
        $sender->expects($this->once())->method('send')->with($request)->willReturn($deferred->promise());

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['timeout' => 2]);
        $promise = $transaction->send($request);

        $deferred->reject(new \RuntimeException('Request failed'));
        $stream->close();

        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $promise->then(null, $this->expectCallableOnce()); // avoid reporting unhandled rejection
    }

    public function testTimeoutExplicitOptionWillRejectWhenTimerFiresAfterStreamingRequestBodyCloses()
    {
        $timeout = null;
        $timer = $this->createMock(TimerInterface::class);
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())->method('addTimer')->with(2, $this->callback(function ($cb) use (&$timeout) {
            $timeout = $cb;
            return true;
        }))->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://example.com', [], new ReadableBodyStream($stream));

        $sender = $this->createMock(Sender::class);
        $sender->expects($this->once())->method('send')->with($request)->willReturn(new Promise(function () { }));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['timeout' => 2]);
        $promise = $transaction->send($request);

        $stream->close();

        $this->assertNotNull($timeout);
        $timeout();

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Request timed out after 2 seconds', $exception->getMessage());
    }

    public function testReceivingErrorResponseWillRejectWithResponseException()
    {
        $request = $this->createMock(RequestInterface::class);
        $response = new Response(404);
        $loop = $this->createMock(LoopInterface::class);

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($request)->willReturn(resolve($response));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['timeout' => -1]);
        $promise = $transaction->send($request);

        $exception = null;
        $promise->then(null, function ($reason) use (&$exception) {
            $exception = $reason;
        });

        assert($exception instanceof ResponseException);
        $this->assertEquals(404, $exception->getCode());
        $this->assertSame($response, $exception->getResponse());
    }

    public function testReceivingStreamingBodyWillResolveWithBufferedResponseByDefault()
    {
        $stream = new ThroughStream();
        Loop::addTimer(0.001, function () use ($stream) {
            $stream->emit('data', ['hello world']);
            $stream->close();
        });

        $request = $this->createMock(RequestInterface::class);
        $response = new Response(200, [], new ReadableBodyStream($stream));

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($request)->willReturn(resolve($response));

        $transaction = new Transaction($sender, Loop::get());
        $promise = $transaction->send($request);

        $response = await($promise);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('hello world', (string)$response->getBody());
    }

    public function testReceivingStreamingBodyWithContentLengthExceedingMaximumResponseBufferWillRejectAndCloseResponseStreamImmediately()
    {
        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $request = $this->createMock(RequestInterface::class);

        $response = new Response(200, ['Content-Length' => '100000000'], new ReadableBodyStream($stream, 100000000));

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($request)->willReturn(resolve($response));

        $transaction = new Transaction($sender, Loop::get());

        $promise = $transaction->send($request);

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertFalse($stream->isWritable());

        assert($exception instanceof \OverflowException);
        $this->assertInstanceOf(\OverflowException::class, $exception);
        $this->assertEquals('Response body size of 100000000 bytes exceeds maximum of 16777216 bytes', $exception->getMessage());
        $this->assertEquals(defined('SOCKET_EMSGSIZE') ? \SOCKET_EMSGSIZE : 90, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testReceivingStreamingBodyWithContentsExceedingMaximumResponseBufferWillRejectAndCloseResponseStreamWhenBufferExceedsLimit()
    {
        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $request = $this->createMock(RequestInterface::class);

        $response = new Response(200, [], new ReadableBodyStream($stream));

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($request)->willReturn(resolve($response));

        $transaction = new Transaction($sender, Loop::get());
        $transaction = $transaction->withOptions(['maximumSize' => 10]);
        $promise = $transaction->send($request);

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertTrue($stream->isWritable());
        $stream->write('hello wörld');
        $this->assertFalse($stream->isWritable());

        assert($exception instanceof \OverflowException);
        $this->assertInstanceOf(\OverflowException::class, $exception);
        $this->assertEquals('Response body size exceeds maximum of 10 bytes', $exception->getMessage());
        $this->assertEquals(defined('SOCKET_EMSGSIZE') ? \SOCKET_EMSGSIZE : 90, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testReceivingStreamingBodyWillRejectWhenStreamEmitsError()
    {
        $stream = new ThroughStream(function ($data) {
            throw new \UnexpectedValueException('Unexpected ' . $data, 42);
        });

        $request = $this->createMock(RequestInterface::class);
        $response = new Response(200, [], new ReadableBodyStream($stream));

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($request)->willReturn(resolve($response));

        $transaction = new Transaction($sender, Loop::get());
        $promise = $transaction->send($request);

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertTrue($stream->isWritable());
        $stream->write('Foo');
        $this->assertFalse($stream->isWritable());

        assert($exception instanceof \RuntimeException);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Error while buffering response body: Unexpected Foo', $exception->getMessage());
        $this->assertEquals(42, $exception->getCode());
        $this->assertInstanceOf(\UnexpectedValueException::class, $exception->getPrevious());
    }

    public function testCancelBufferingResponseWillCloseStreamAndReject()
    {
        $stream = $this->createMock(ReadableStreamInterface::class);
        $stream->expects($this->any())->method('isReadable')->willReturn(true);
        $stream->expects($this->once())->method('close');

        $request = $this->createMock(RequestInterface::class);
        $response = new Response(200, [], new ReadableBodyStream($stream));

        // mock sender to resolve promise with the given $response in response to the given $request
        $deferred = new Deferred();
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($request)->willReturn($deferred->promise());

        $transaction = new Transaction($sender, Loop::get());
        $promise = $transaction->send($request);

        $deferred->resolve($response);
        $promise->cancel();

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        assert($exception instanceof \RuntimeException);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Cancelled buffering response body', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testReceivingStreamingBodyWillResolveWithStreamingResponseIfStreamingIsEnabled()
    {
        $loop = $this->createMock(LoopInterface::class);

        $request = $this->createMock(RequestInterface::class);
        $response = new Response(200, [], new ReadableBodyStream($this->createMock(ReadableStreamInterface::class)));

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($request)->willReturn(resolve($response));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['streaming' => true, 'timeout' => -1]);
        $promise = $transaction->send($request);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('', (string)$response->getBody());
    }

    public function testResponseCode304WithoutLocationWillResolveWithResponseAsIs()
    {
        $loop = $this->createMock(LoopInterface::class);

        // conditional GET request will respond with 304 (Not Modified
        $request = new Request('GET', 'http://example.com', ['If-None-Match' => '"abc"']);
        $response = new Response(304, ['ETag' => '"abc"']);
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($request)->willReturn(resolve($response));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['timeout' => -1]);
        $promise = $transaction->send($request);

        $promise->then($this->expectCallableOnceWith($response));
    }

    public function testCustomRedirectResponseCode333WillFollowLocationHeaderAndSendRedirectedRequest()
    {
        $loop = $this->createMock(LoopInterface::class);

        // original GET request will respond with custom 333 redirect status code and follow location header
        $requestOriginal = new Request('GET', 'http://example.com');
        $response = new Response(333, ['Location' => 'foo']);
        $sender = $this->makeSenderMock();
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            [$requestOriginal],
            [$this->callback(function (RequestInterface $request) {
                return $request->getMethod() === 'GET' && (string)$request->getUri() === 'http://example.com/foo';
            })]
        )->willReturnOnConsecutiveCalls(
            resolve($response),
            new Promise(function () { })
        );

        $transaction = new Transaction($sender, $loop);
        $transaction->send($requestOriginal);
    }

    public function testFollowingRedirectWithSpecifiedHeaders()
    {
        $loop = $this->createMock(LoopInterface::class);

        $customHeaders = ['User-Agent' => 'Chrome'];
        $requestWithUserAgent = new Request('GET', 'http://example.com', $customHeaders);
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithUserAgent
        $redirectResponse = new Response(301, ['Location' => 'http://redirect.com']);

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithUserAgent
        $okResponse = new Response(200);
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            [$this->anything()],
            [$this->callback(function (RequestInterface $request) {
                $this->assertEquals(['Chrome'], $request->getHeader('User-Agent'));
                return true;
            })]
        )->willReturnOnConsecutiveCalls(
            resolve($redirectResponse),
            resolve($okResponse)
        );

        $transaction = new Transaction($sender, $loop);
        $transaction->send($requestWithUserAgent);
    }

    public function testRemovingAuthorizationHeaderWhenChangingHostnamesDuringRedirect()
    {
        $loop = $this->createMock(LoopInterface::class);

        $customHeaders = ['Authorization' => 'secret'];
        $requestWithAuthorization = new Request('GET', 'http://example.com', $customHeaders);
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithAuthorization
        $redirectResponse = new Response(301, ['Location' => 'http://redirect.com']);

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithAuthorization
        $okResponse = new Response(200);
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            [$this->anything()],
            [$this->callback(function (RequestInterface $request) {
                $this->assertFalse($request->hasHeader('Authorization'));
                return true;
            })]
        )->willReturnOnConsecutiveCalls(
            resolve($redirectResponse),
            resolve($okResponse)
        );

        $transaction = new Transaction($sender, $loop);
        $transaction->send($requestWithAuthorization);
    }

    public function testAuthorizationHeaderIsForwardedWhenRedirectingToSameDomain()
    {
        $loop = $this->createMock(LoopInterface::class);

        $customHeaders = ['Authorization' => 'secret'];
        $requestWithAuthorization = new Request('GET', 'http://example.com', $customHeaders);
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithAuthorization
        $redirectResponse = new Response(301, ['Location' => 'http://example.com/new']);

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithAuthorization
        $okResponse = new Response(200);
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            [$this->anything()],
            [$this->callback(function (RequestInterface $request) {
                $this->assertEquals(['secret'], $request->getHeader('Authorization'));
                return true;
            })]
        )->willReturnOnConsecutiveCalls(
            resolve($redirectResponse),
            resolve($okResponse)
        );

        $transaction = new Transaction($sender, $loop);
        $transaction->send($requestWithAuthorization);
    }

    public function testAuthorizationHeaderIsForwardedWhenLocationContainsAuthentication()
    {
        $loop = $this->createMock(LoopInterface::class);

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithAuthorization
        $redirectResponse = new Response(301, ['Location' => 'http://user:pass@example.com/new']);

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithAuthorization
        $okResponse = new Response(200);
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            [$this->anything()],
            [$this->callback(function (RequestInterface $request) {
                $this->assertEquals('user:pass', $request->getUri()->getUserInfo());
                $this->assertFalse($request->hasHeader('Authorization'));
                return true;
            })]
        )->willReturnOnConsecutiveCalls(
            resolve($redirectResponse),
            resolve($okResponse)
        );

        $transaction = new Transaction($sender, $loop);
        $transaction->send($request);
    }

    public function testSomeRequestHeadersShouldBeRemovedWhenRedirecting()
    {
        $loop = $this->createMock(LoopInterface::class);

        $customHeaders = [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Length' => '111'
        ];

        $requestWithCustomHeaders = new Request('GET', 'http://example.com', $customHeaders);
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithCustomHeaders
        $redirectResponse = new Response(301, ['Location' => 'http://example.com/new']);

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithCustomHeaders
        $okResponse = new Response(200);
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            [$this->anything()],
            [$this->callback(function (RequestInterface $request) {
                $this->assertFalse($request->hasHeader('Content-Type'));
                $this->assertFalse($request->hasHeader('Content-Length'));
                return true;
            })]
        )->willReturnOnConsecutiveCalls(
            resolve($redirectResponse),
            resolve($okResponse)
        );

        $transaction = new Transaction($sender, $loop);
        $transaction->send($requestWithCustomHeaders);
    }

    public function testRequestMethodShouldBeChangedWhenRedirectingWithSeeOther()
    {
        $loop = $this->createMock(LoopInterface::class);

        $customHeaders = [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Length' => '111'
        ];

        $request = new Request('POST', 'http://example.com', $customHeaders);
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $request
        $redirectResponse = new Response(303, ['Location' => 'http://example.com/new']);

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $request
        $okResponse = new Response(200);
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            [$this->anything()],
            [$this->callback(function (RequestInterface $request) {
                $this->assertEquals('GET', $request->getMethod());
                $this->assertFalse($request->hasHeader('Content-Type'));
                $this->assertFalse($request->hasHeader('Content-Length'));
                return true;
            })]
        )->willReturnOnConsecutiveCalls(
            resolve($redirectResponse),
            resolve($okResponse)
        );

        $transaction = new Transaction($sender, $loop);
        $transaction->send($request);
    }

    public function testRequestMethodAndBodyShouldNotBeChangedWhenRedirectingWith307Or308()
    {
        $loop = $this->createMock(LoopInterface::class);

        $customHeaders = [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Length' => '111'
        ];

        $request = new Request('POST', 'http://example.com', $customHeaders, '{"key":"value"}');
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $request
        $redirectResponse = new Response(307, ['Location' => 'http://example.com/new']);

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $request
        $okResponse = new Response(200);
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            [$this->anything()],
            [$this->callback(function (RequestInterface $request) {
                $this->assertEquals('POST', $request->getMethod());
                $this->assertEquals('{"key":"value"}', (string)$request->getBody());
                $this->assertEquals(
                    [
                        'Content-Type' => ['text/html; charset=utf-8'],
                        'Content-Length' => ['111'],
                        'Host' => ['example.com']
                    ],
                    $request->getHeaders()
                );
                return true;
            })]
        )->willReturnOnConsecutiveCalls(
            resolve($redirectResponse),
            resolve($okResponse)
        );

        $transaction = new Transaction($sender, $loop);
        $transaction->send($request);
    }

    public function testRedirectingStreamingBodyWith307Or308ShouldThrowCantRedirectStreamException()
    {
        $loop = $this->createMock(LoopInterface::class);

        $customHeaders = [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Length' => '111'
        ];

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://example.com', $customHeaders, new ReadableBodyStream($stream));
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $request
        $redirectResponse = new Response(307, ['Location' => 'http://example.com/new']);

        $sender->expects($this->once())->method('send')->withConsecutive(
            [$this->anything()]
        )->willReturnOnConsecutiveCalls(
            resolve($redirectResponse)
        );

        $transaction = new Transaction($sender, $loop);
        $promise = $transaction->send($request);

        $exception = null;
        $promise->then(null, function ($reason) use (&$exception) {
            $exception = $reason;
        });

        assert($exception instanceof \RuntimeException);
        $this->assertEquals('Unable to redirect request with streaming body', $exception->getMessage());
    }

    public function testCancelTransactionWillCancelRequest()
    {
        $loop = $this->createMock(LoopInterface::class);

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        $pending = new Promise(function () { }, $this->expectCallableOnce());

        // mock sender to return pending promise which should be cancelled when cancelling result
        $sender->expects($this->once())->method('send')->willReturn($pending);

        $transaction = new Transaction($sender, $loop);
        $promise = $transaction->send($request);

        $promise->cancel();
    }

    public function testCancelTransactionWillCancelTimeoutTimer()
    {
        $timer = $this->createMock(TimerInterface::class);
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())->method('addTimer')->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        $pending = new Promise(function () { }, function () { throw new \RuntimeException(); });

        // mock sender to return pending promise which should be cancelled when cancelling result
        $sender->expects($this->once())->method('send')->willReturn($pending);

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(['timeout' => 2]);
        $promise = $transaction->send($request);

        $promise->cancel();
    }

    public function testCancelTransactionWillCancelRedirectedRequest()
    {
        $loop = $this->createMock(LoopInterface::class);

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        $redirectResponse = new Response(301, ['Location' => 'http://example.com/new']);

        $pending = new Promise(function () { }, $this->expectCallableOnce());

        // mock sender to return pending promise which should be cancelled when cancelling result
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            [$this->anything()],
            [$this->anything()]
        )->willReturnOnConsecutiveCalls(
            resolve($redirectResponse),
            $pending
        );

        $transaction = new Transaction($sender, $loop);
        $promise = $transaction->send($request);

        $promise->cancel();
    }

    public function testCancelTransactionWillCancelRedirectedRequestAgain()
    {
        $loop = $this->createMock(LoopInterface::class);

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        $first = new Deferred();

        $second = new Promise(function () { }, $this->expectCallableOnce());

        // mock sender to return pending promise which should be cancelled when cancelling result
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            [$this->anything()],
            [$this->anything()]
        )->willReturnOnConsecutiveCalls(
            $first->promise(),
            $second
        );

        $transaction = new Transaction($sender, $loop);
        $promise = $transaction->send($request);

        // mock sender to resolve promise with the given $redirectResponse in
        $first->resolve(new Response(301, ['Location' => 'http://example.com/new']));

        $promise->cancel();
    }

    public function testCancelTransactionWillCloseBufferingStream()
    {
        $loop = $this->createMock(LoopInterface::class);

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        $body = new ThroughStream();
        $body->on('close', $this->expectCallableOnce());

        // mock sender to resolve promise with the given $redirectResponse
        $deferred = new Deferred();
        $sender->expects($this->once())->method('send')->willReturn($deferred->promise());

        $transaction = new Transaction($sender, $loop);
        $promise = $transaction->send($request);

        $redirectResponse = new Response(301, ['Location' => 'http://example.com/new'], new ReadableBodyStream($body));
        $deferred->resolve($redirectResponse);

        $promise->cancel();
    }

    public function testCancelTransactionWillCloseBufferingStreamAgain()
    {
        $loop = $this->createMock(LoopInterface::class);

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        $first = new Deferred();
        $sender->expects($this->once())->method('send')->willReturn($first->promise());

        $transaction = new Transaction($sender, $loop);
        $promise = $transaction->send($request);

        $body = new ThroughStream();
        $body->on('close', $this->expectCallableOnce());

        // mock sender to resolve promise with the given $redirectResponse in
        $first->resolve(new Response(301, ['Location' => 'http://example.com/new'], new ReadableBodyStream($body)));
        $promise->cancel();
    }

    public function testCancelTransactionShouldCancelSendingPromise()
    {
        $loop = $this->createMock(LoopInterface::class);

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        $redirectResponse = new Response(301, ['Location' => 'http://example.com/new']);

        $pending = new Promise(function () { }, $this->expectCallableOnce());

        // mock sender to return pending promise which should be cancelled when cancelling result
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            [$this->anything()],
            [$this->anything()]
        )->willReturnOnConsecutiveCalls(
            resolve($redirectResponse),
            $pending
        );

        $transaction = new Transaction($sender, $loop);
        $promise = $transaction->send($request);

        $promise->cancel();
    }

    /**
     * @return MockObject
     */
    private function makeSenderMock()
    {
        return $this->createMock(Sender::class);
    }
}
