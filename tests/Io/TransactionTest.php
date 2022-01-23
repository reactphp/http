<?php

namespace React\Tests\Http\Io;

use Clue\React\Block;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\RequestInterface;
use RingCentral\Psr7\Response;
use React\Http\Io\Transaction;
use React\Http\Message\ResponseException;
use React\EventLoop\Loop;
use React\Promise;
use React\Promise\Deferred;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;
use RingCentral\Psr7\Request;
use React\Http\Io\ReadableBodyStream;

class TransactionTest extends TestCase
{
    public function testWithOptionsReturnsNewInstanceWithChangedOption()
    {
        $sender = $this->makeSenderMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $transaction = new Transaction($sender, $loop);

        $new = $transaction->withOptions(array('followRedirects' => false));

        $this->assertInstanceOf('React\Http\Io\Transaction', $new);
        $this->assertNotSame($transaction, $new);

        $ref = new \ReflectionProperty($new, 'followRedirects');
        $ref->setAccessible(true);

        $this->assertFalse($ref->getValue($new));
    }

    public function testWithOptionsDoesNotChangeOriginalInstance()
    {
        $sender = $this->makeSenderMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $transaction = new Transaction($sender, $loop);

        $transaction->withOptions(array('followRedirects' => false));

        $ref = new \ReflectionProperty($transaction, 'followRedirects');
        $ref->setAccessible(true);

        $this->assertTrue($ref->getValue($transaction));
    }

    public function testWithOptionsNullValueReturnsNewInstanceWithDefaultOption()
    {
        $sender = $this->makeSenderMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $transaction = new Transaction($sender, $loop);

        $transaction = $transaction->withOptions(array('followRedirects' => false));
        $transaction = $transaction->withOptions(array('followRedirects' => null));

        $ref = new \ReflectionProperty($transaction, 'followRedirects');
        $ref->setAccessible(true);

        $this->assertTrue($ref->getValue($transaction));
    }

    public function testTimeoutExplicitOptionWillStartTimeoutTimer()
    {
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with(2, $this->anything())->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();

        $sender = $this->getMockBuilder('React\Http\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 2));
        $promise = $transaction->send($request);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
    }

    public function testTimeoutImplicitFromIniWillStartTimeoutTimer()
    {
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with(2, $this->anything())->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();

        $sender = $this->getMockBuilder('React\Http\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $loop);

        $old = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', '2');
        $promise = $transaction->send($request);
        ini_set('default_socket_timeout', $old);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
    }

    public function testTimeoutExplicitOptionWillRejectWhenTimerFires()
    {
        $timeout = null;
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with(2, $this->callback(function ($cb) use (&$timeout) {
            $timeout = $cb;
            return true;
        }))->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();

        $sender = $this->getMockBuilder('React\Http\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 2));
        $promise = $transaction->send($request);

        $this->assertNotNull($timeout);
        $timeout();

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertInstanceOf('RuntimeException', $exception);
        $this->assertEquals('Request timed out after 2 seconds', $exception->getMessage());
    }

    public function testTimeoutExplicitOptionWillNotStartTimeoutWhenSenderResolvesImmediately()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->never())->method('addTimer');

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();
        $response = new Response(200, array(), '');

        $sender = $this->getMockBuilder('React\Http\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 0.001));
        $promise = $transaction->send($request);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
        $promise->then($this->expectCallableOnceWith($response));
    }

    public function testTimeoutExplicitOptionWillCancelTimeoutTimerWhenSenderResolvesLaterOn()
    {
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();
        $response = new Response(200, array(), '');

        $deferred = new Deferred();
        $sender = $this->getMockBuilder('React\Http\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn($deferred->promise());

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 0.001));
        $promise = $transaction->send($request);

        $deferred->resolve($response);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
        $promise->then($this->expectCallableOnceWith($response));
    }

    public function testTimeoutExplicitOptionWillNotStartTimeoutWhenSenderRejectsImmediately()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->never())->method('addTimer');

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();
        $exception = new \RuntimeException();

        $sender = $this->getMockBuilder('React\Http\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\reject($exception));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 0.001));
        $promise = $transaction->send($request);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
        $promise->then(null, $this->expectCallableOnceWith($exception));
    }

    public function testTimeoutExplicitOptionWillCancelTimeoutTimerWhenSenderRejectsLaterOn()
    {
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();

        $deferred = new Deferred();
        $sender = $this->getMockBuilder('React\Http\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn($deferred->promise());

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 0.001));
        $promise = $transaction->send($request);

        $exception = new \RuntimeException();
        $deferred->reject($exception);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
        $promise->then(null, $this->expectCallableOnceWith($exception));
    }

    public function testTimeoutExplicitNegativeWillNotStartTimeoutTimer()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->never())->method('addTimer');

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();

        $sender = $this->getMockBuilder('React\Http\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('timeout' => -1));
        $promise = $transaction->send($request);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
    }

    public function testTimeoutExplicitOptionWillNotStartTimeoutTimerWhenRequestBodyIsStreaming()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->never())->method('addTimer');

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://example.com', array(), new ReadableBodyStream($stream));

        $sender = $this->getMockBuilder('React\Http\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 2));
        $promise = $transaction->send($request);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
    }

    public function testTimeoutExplicitOptionWillStartTimeoutTimerWhenStreamingRequestBodyIsAlreadyClosed()
    {
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with(2, $this->anything())->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $stream = new ThroughStream();
        $stream->close();
        $request = new Request('POST', 'http://example.com', array(), new ReadableBodyStream($stream));

        $sender = $this->getMockBuilder('React\Http\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 2));
        $promise = $transaction->send($request);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
    }

    public function testTimeoutExplicitOptionWillStartTimeoutTimerWhenStreamingRequestBodyClosesWhileSenderIsStillPending()
    {
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with(2, $this->anything())->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://example.com', array(), new ReadableBodyStream($stream));

        $sender = $this->getMockBuilder('React\Http\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 2));
        $promise = $transaction->send($request);

        $stream->close();

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
    }

    public function testTimeoutExplicitOptionWillNotStartTimeoutTimerWhenStreamingRequestBodyClosesAfterSenderRejects()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->never())->method('addTimer');

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://example.com', array(), new ReadableBodyStream($stream));

        $deferred = new Deferred();
        $sender = $this->getMockBuilder('React\Http\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn($deferred->promise());

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 2));
        $promise = $transaction->send($request);

        $deferred->reject(new \RuntimeException('Request failed'));
        $stream->close();

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
    }

    public function testTimeoutExplicitOptionWillRejectWhenTimerFiresAfterStreamingRequestBodyCloses()
    {
        $timeout = null;
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with(2, $this->callback(function ($cb) use (&$timeout) {
            $timeout = $cb;
            return true;
        }))->willReturn($timer);
        $loop->expects($this->never())->method('cancelTimer');

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://example.com', array(), new ReadableBodyStream($stream));

        $sender = $this->getMockBuilder('React\Http\Io\Sender')->disableOriginalConstructor()->getMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(new \React\Promise\Promise(function () { }));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 2));
        $promise = $transaction->send($request);

        $stream->close();

        $this->assertNotNull($timeout);
        $timeout();

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertInstanceOf('RuntimeException', $exception);
        $this->assertEquals('Request timed out after 2 seconds', $exception->getMessage());
    }

    public function testReceivingErrorResponseWillRejectWithResponseException()
    {
        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();
        $response = new Response(404);
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('timeout' => -1));
        $promise = $transaction->send($request);

        try {
            Block\await($promise, $loop);
            $this->fail();
        } catch (ResponseException $exception) {
            $this->assertEquals(404, $exception->getCode());
            $this->assertSame($response, $exception->getResponse());
        }
    }

    public function testReceivingStreamingBodyWillResolveWithBufferedResponseByDefault()
    {
        $stream = new ThroughStream();
        Loop::addTimer(0.001, function () use ($stream) {
            $stream->emit('data', array('hello world'));
            $stream->close();
        });

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();
        $response = new Response(200, array(), new ReadableBodyStream($stream));

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($sender, Loop::get());
        $promise = $transaction->send($request);

        $response = Block\await($promise);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('hello world', (string)$response->getBody());
    }

    public function testReceivingStreamingBodyWithSizeExceedingMaximumResponseBufferWillRejectAndCloseResponseStream()
    {
        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();

        $response = new Response(200, array('Content-Length' => '100000000'), new ReadableBodyStream($stream, 100000000));

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($sender, Loop::get());
        $promise = $transaction->send($request);

        $this->setExpectedException('OverflowException');
        Block\await($promise, null, 0.001);
    }

    public function testCancelBufferingResponseWillCloseStreamAndReject()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $stream->expects($this->any())->method('isReadable')->willReturn(true);
        $stream->expects($this->once())->method('close');

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();
        $response = new Response(200, array(), new ReadableBodyStream($stream));

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($sender, Loop::get());
        $promise = $transaction->send($request);
        $promise->cancel();

        $this->setExpectedException('RuntimeException');
        Block\await($promise, null, 0.001);
    }

    public function testReceivingStreamingBodyWillResolveWithStreamingResponseIfStreamingIsEnabled()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = $this->getMockBuilder('Psr\Http\Message\RequestInterface')->getMock();
        $response = new Response(200, array(), new ReadableBodyStream($this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock()));

        // mock sender to resolve promise with the given $response in response to the given $request
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($this->equalTo($request))->willReturn(Promise\resolve($response));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('streaming' => true, 'timeout' => -1));
        $promise = $transaction->send($request);

        $response = Block\await($promise, $loop);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('', (string)$response->getBody());
    }

    public function testResponseCode304WithoutLocationWillResolveWithResponseAsIs()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        // conditional GET request will respond with 304 (Not Modified
        $request = new Request('GET', 'http://example.com', array('If-None-Match' => '"abc"'));
        $response = new Response(304, array('ETag' => '"abc"'));
        $sender = $this->makeSenderMock();
        $sender->expects($this->once())->method('send')->with($request)->willReturn(Promise\resolve($response));

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('timeout' => -1));
        $promise = $transaction->send($request);

        $promise->then($this->expectCallableOnceWith($response));
    }

    public function testCustomRedirectResponseCode333WillFollowLocationHeaderAndSendRedirectedRequest()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        // original GET request will respond with custom 333 redirect status code and follow location header
        $requestOriginal = new Request('GET', 'http://example.com');
        $response = new Response(333, array('Location' => 'foo'));
        $sender = $this->makeSenderMock();
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            array($requestOriginal),
            array($this->callback(function (RequestInterface $request) {
                return $request->getMethod() === 'GET' && (string)$request->getUri() === 'http://example.com/foo';
            }))
        )->willReturnOnConsecutiveCalls(
            Promise\resolve($response),
            new \React\Promise\Promise(function () { })
        );

        $transaction = new Transaction($sender, $loop);
        $transaction->send($requestOriginal);
    }

    public function testFollowingRedirectWithSpecifiedHeaders()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $customHeaders = array('User-Agent' => 'Chrome');
        $requestWithUserAgent = new Request('GET', 'http://example.com', $customHeaders);
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithUserAgent
        $redirectResponse = new Response(301, array('Location' => 'http://redirect.com'));

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithUserAgent
        $okResponse = new Response(200);
        $that = $this;
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            array($this->anything()),
            array($this->callback(function (RequestInterface $request) use ($that) {
                $that->assertEquals(array('Chrome'), $request->getHeader('User-Agent'));
                return true;
            }))
        )->willReturnOnConsecutiveCalls(
            Promise\resolve($redirectResponse),
            Promise\resolve($okResponse)
        );

        $transaction = new Transaction($sender, $loop);
        $transaction->send($requestWithUserAgent);
    }

    public function testRemovingAuthorizationHeaderWhenChangingHostnamesDuringRedirect()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $customHeaders = array('Authorization' => 'secret');
        $requestWithAuthorization = new Request('GET', 'http://example.com', $customHeaders);
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithAuthorization
        $redirectResponse = new Response(301, array('Location' => 'http://redirect.com'));

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithAuthorization
        $okResponse = new Response(200);
        $that = $this;
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            array($this->anything()),
            array($this->callback(function (RequestInterface $request) use ($that) {
                $that->assertFalse($request->hasHeader('Authorization'));
                return true;
            }))
        )->willReturnOnConsecutiveCalls(
            Promise\resolve($redirectResponse),
            Promise\resolve($okResponse)
        );

        $transaction = new Transaction($sender, $loop);
        $transaction->send($requestWithAuthorization);
    }

    public function testAuthorizationHeaderIsForwardedWhenRedirectingToSameDomain()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $customHeaders = array('Authorization' => 'secret');
        $requestWithAuthorization = new Request('GET', 'http://example.com', $customHeaders);
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithAuthorization
        $redirectResponse = new Response(301, array('Location' => 'http://example.com/new'));

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithAuthorization
        $okResponse = new Response(200);
        $that = $this;
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            array($this->anything()),
            array($this->callback(function (RequestInterface $request) use ($that) {
                $that->assertEquals(array('secret'), $request->getHeader('Authorization'));
                return true;
            }))
        )->willReturnOnConsecutiveCalls(
            Promise\resolve($redirectResponse),
            Promise\resolve($okResponse)
        );

        $transaction = new Transaction($sender, $loop);
        $transaction->send($requestWithAuthorization);
    }

    public function testAuthorizationHeaderIsForwardedWhenLocationContainsAuthentication()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithAuthorization
        $redirectResponse = new Response(301, array('Location' => 'http://user:pass@example.com/new'));

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithAuthorization
        $okResponse = new Response(200);
        $that = $this;
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            array($this->anything()),
            array($this->callback(function (RequestInterface $request) use ($that) {
                $that->assertEquals('user:pass', $request->getUri()->getUserInfo());
                $that->assertFalse($request->hasHeader('Authorization'));
                return true;
            }))
        )->willReturnOnConsecutiveCalls(
            Promise\resolve($redirectResponse),
            Promise\resolve($okResponse)
        );

        $transaction = new Transaction($sender, $loop);
        $transaction->send($request);
    }

    public function testSomeRequestHeadersShouldBeRemovedWhenRedirecting()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $customHeaders = array(
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Length' => '111',
        );

        $requestWithCustomHeaders = new Request('GET', 'http://example.com', $customHeaders);
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        // response to the given $requestWithCustomHeaders
        $redirectResponse = new Response(301, array('Location' => 'http://example.com/new'));

        // mock sender to resolve promise with the given $okResponse in
        // response to the given $requestWithCustomHeaders
        $okResponse = new Response(200);
        $that = $this;
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            array($this->anything()),
            array($this->callback(function (RequestInterface $request) use ($that) {
                $that->assertFalse($request->hasHeader('Content-Type'));
                $that->assertFalse($request->hasHeader('Content-Length'));
                return true;;
            }))
        )->willReturnOnConsecutiveCalls(
            Promise\resolve($redirectResponse),
            Promise\resolve($okResponse)
        );

        $transaction = new Transaction($sender, $loop);
        $transaction->send($requestWithCustomHeaders);
    }

    public function testCancelTransactionWillCancelRequest()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        $pending = new \React\Promise\Promise(function () { }, $this->expectCallableOnce());

        // mock sender to return pending promise which should be cancelled when cancelling result
        $sender->expects($this->once())->method('send')->willReturn($pending);

        $transaction = new Transaction($sender, $loop);
        $promise = $transaction->send($request);

        $promise->cancel();
    }

    public function testCancelTransactionWillCancelTimeoutTimer()
    {
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        $pending = new \React\Promise\Promise(function () { }, function () { throw new \RuntimeException(); });

        // mock sender to return pending promise which should be cancelled when cancelling result
        $sender->expects($this->once())->method('send')->willReturn($pending);

        $transaction = new Transaction($sender, $loop);
        $transaction = $transaction->withOptions(array('timeout' => 2));
        $promise = $transaction->send($request);

        $promise->cancel();
    }

    public function testCancelTransactionWillCancelRedirectedRequest()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        $redirectResponse = new Response(301, array('Location' => 'http://example.com/new'));

        $pending = new \React\Promise\Promise(function () { }, $this->expectCallableOnce());

        // mock sender to return pending promise which should be cancelled when cancelling result
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            array($this->anything()),
            array($this->anything())
        )->willReturnOnConsecutiveCalls(
            Promise\resolve($redirectResponse),
            $pending
        );

        $transaction = new Transaction($sender, $loop);
        $promise = $transaction->send($request);

        $promise->cancel();
    }

    public function testCancelTransactionWillCancelRedirectedRequestAgain()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        $first = new Deferred();

        $second = new \React\Promise\Promise(function () { }, $this->expectCallableOnce());

        // mock sender to return pending promise which should be cancelled when cancelling result
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            array($this->anything()),
            array($this->anything())
        )->willReturnOnConsecutiveCalls(
            $first->promise(),
            $second
        );

        $transaction = new Transaction($sender, $loop);
        $promise = $transaction->send($request);

        // mock sender to resolve promise with the given $redirectResponse in
        $first->resolve(new Response(301, array('Location' => 'http://example.com/new')));

        $promise->cancel();
    }

    public function testCancelTransactionWillCloseBufferingStream()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        $body = new ThroughStream();
        $body->on('close', $this->expectCallableOnce());

        // mock sender to resolve promise with the given $redirectResponse in
        $redirectResponse = new Response(301, array('Location' => 'http://example.com/new'), new ReadableBodyStream($body));
        $sender->expects($this->once())->method('send')->willReturn(Promise\resolve($redirectResponse));

        $transaction = new Transaction($sender, $loop);
        $promise = $transaction->send($request);

        $promise->cancel();
    }

    public function testCancelTransactionWillCloseBufferingStreamAgain()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        $first = new Deferred();
        $sender->expects($this->once())->method('send')->willReturn($first->promise());

        $transaction = new Transaction($sender, $loop);
        $promise = $transaction->send($request);

        $body = new ThroughStream();
        $body->on('close', $this->expectCallableOnce());

        // mock sender to resolve promise with the given $redirectResponse in
        $first->resolve(new Response(301, array('Location' => 'http://example.com/new'), new ReadableBodyStream($body)));
        $promise->cancel();
    }

    public function testCancelTransactionShouldCancelSendingPromise()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $request = new Request('GET', 'http://example.com');
        $sender = $this->makeSenderMock();

        // mock sender to resolve promise with the given $redirectResponse in
        $redirectResponse = new Response(301, array('Location' => 'http://example.com/new'));

        $pending = new \React\Promise\Promise(function () { }, $this->expectCallableOnce());

        // mock sender to return pending promise which should be cancelled when cancelling result
        $sender->expects($this->exactly(2))->method('send')->withConsecutive(
            array($this->anything()),
            array($this->anything())
        )->willReturnOnConsecutiveCalls(
            Promise\resolve($redirectResponse),
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
        return $this->getMockBuilder('React\Http\Io\Sender')->disableOriginalConstructor()->getMock();
    }
}
