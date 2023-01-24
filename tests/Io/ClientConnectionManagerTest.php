<?php

namespace React\Tests\Http\Io;

use RingCentral\Psr7\Uri;
use React\Http\Io\ClientConnectionManager;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Tests\Http\TestCase;

class ClientConnectionManagerTest extends TestCase
{
    public function testConnectWithHttpsUriShouldConnectToTlsWithDefaultPort()
    {
        $promise = new Promise(function () { });
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('tls://reactphp.org:443')->willReturn($promise);

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $connectionManager = new ClientConnectionManager($connector, $loop);

        $ret = $connectionManager->connect(new Uri('https://reactphp.org/'));

        assert($ret instanceof PromiseInterface);
        $this->assertSame($promise, $ret);
    }

    public function testConnectWithHttpUriShouldConnectToTcpWithDefaultPort()
    {
        $promise = new Promise(function () { });
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('reactphp.org:80')->willReturn($promise);

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $connectionManager = new ClientConnectionManager($connector, $loop);

        $ret = $connectionManager->connect(new Uri('http://reactphp.org/'));
        $this->assertSame($promise, $ret);
    }

    public function testConnectWithExplicitPortShouldConnectWithGivenPort()
    {
        $promise = new Promise(function () { });
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('reactphp.org:8080')->willReturn($promise);

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $connectionManager = new ClientConnectionManager($connector, $loop);

        $ret = $connectionManager->connect(new Uri('http://reactphp.org:8080/'));
        $this->assertSame($promise, $ret);
    }

    public function testConnectWithInvalidSchemeShouldRejectWithException()
    {
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->never())->method('connect');

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $connectionManager = new ClientConnectionManager($connector, $loop);

        $promise = $connectionManager->connect(new Uri('ftp://reactphp.org/'));

        $exception = null;
        $promise->then(null, function ($reason) use (&$exception) {
            $exception = $reason;
        });

        $this->assertInstanceOf('InvalidArgumentException', $exception);
        $this->assertEquals('Invalid request URL given', $exception->getMessage());
    }

    public function testConnectWithoutSchemeShouldRejectWithException()
    {
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->never())->method('connect');

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $connectionManager = new ClientConnectionManager($connector, $loop);

        $promise = $connectionManager->connect(new Uri('reactphp.org'));

        $exception = null;
        $promise->then(null, function ($reason) use (&$exception) {
            $exception = $reason;
        });

        $this->assertInstanceOf('InvalidArgumentException', $exception);
        $this->assertEquals('Invalid request URL given', $exception->getMessage());
    }

    public function testConnectReusesIdleConnectionFromPreviousKeepAliveCallWithoutUsingConnectorAndWillAddAndRemoveStreamEventsAndAddAndCancelIdleTimer()
    {
        $connectionToReuse = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();

        $streamHandler = null;
        $connectionToReuse->expects($this->exactly(3))->method('on')->withConsecutive(
            array(
                'close',
                $this->callback(function ($cb) use (&$streamHandler) {
                    $streamHandler = $cb;
                    return true;
                })
            ),
            array(
                'data',
                $this->callback(function ($cb) use (&$streamHandler) {
                    assert($streamHandler instanceof \Closure);
                    return $cb === $streamHandler;
                })
            ),
            array(
                'error',
                $this->callback(function ($cb) use (&$streamHandler) {
                    assert($streamHandler instanceof \Closure);
                    return $cb === $streamHandler;
                })
            )
        );

        $connectionToReuse->expects($this->exactly(3))->method('removeListener')->withConsecutive(
            array(
                'close',
                $this->callback(function ($cb) use (&$streamHandler) {
                    assert($streamHandler instanceof \Closure);
                    return $cb === $streamHandler;
                })
            ),
            array(
                'data',
                $this->callback(function ($cb) use (&$streamHandler) {
                    assert($streamHandler instanceof \Closure);
                    return $cb === $streamHandler;
                })
            ),
            array(
                'error',
                $this->callback(function ($cb) use (&$streamHandler) {
                    assert($streamHandler instanceof \Closure);
                    return $cb === $streamHandler;
                })
            )
        );

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->never())->method('connect');

        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $connectionManager = new ClientConnectionManager($connector, $loop);

        $connectionManager->keepAlive(new Uri('https://reactphp.org/'), $connectionToReuse);

        $promise = $connectionManager->connect(new Uri('https://reactphp.org/'));
        assert($promise instanceof PromiseInterface);

        $connection = null;
        $promise->then(function ($value) use (&$connection) {
            $connection = $value;
        });

        $this->assertSame($connectionToReuse, $connection);
    }

    public function testConnectReusesIdleConnectionFromPreviousKeepAliveCallWithoutUsingConnectorAlsoWhenUriPathAndQueryAndFragmentIsDifferent()
    {
        $connectionToReuse = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->never())->method('connect');

        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $connectionManager = new ClientConnectionManager($connector, $loop);

        $connectionManager->keepAlive(new Uri('https://reactphp.org/http?foo#bar'), $connectionToReuse);

        $promise = $connectionManager->connect(new Uri('https://reactphp.org/http/'));
        assert($promise instanceof PromiseInterface);

        $connection = null;
        $promise->then(function ($value) use (&$connection) {
            $connection = $value;
        });

        $this->assertSame($connectionToReuse, $connection);
    }

    public function testConnectUsesConnectorWithSameUriAndReturnsPromiseForNewConnectionFromConnectorWhenPreviousKeepAliveCallUsedDifferentUri()
    {
        $connectionToReuse = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();

        $promise = new Promise(function () { });
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('tls://reactphp.org:443')->willReturn($promise);

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $connectionManager = new ClientConnectionManager($connector, $loop);

        $connectionManager->keepAlive(new Uri('http://reactphp.org/'), $connectionToReuse);

        $ret = $connectionManager->connect(new Uri('https://reactphp.org/'));

        assert($ret instanceof PromiseInterface);
        $this->assertSame($promise, $ret);
    }

    public function testConnectUsesConnectorForNewConnectionWhenPreviousConnectReusedIdleConnectionFromPreviousKeepAliveCall()
    {
        $firstConnection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $secondConnection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('tls://reactphp.org:443')->willReturn(\React\Promise\resolve($secondConnection));

        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $connectionManager = new ClientConnectionManager($connector, $loop);

        $connectionManager->keepAlive(new Uri('https://reactphp.org/'), $firstConnection);

        $promise = $connectionManager->connect(new Uri('https://reactphp.org/'));
        assert($promise instanceof PromiseInterface);

        $promise = $connectionManager->connect(new Uri('https://reactphp.org/'));
        assert($promise instanceof PromiseInterface);

        $connection = null;
        $promise->then(function ($value) use (&$connection) {
            $connection = $value;
        });

        $this->assertSame($secondConnection, $connection);
    }

    public function testKeepAliveAddsTimerAndDoesNotCloseConnectionImmediately()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->never())->method('close');

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with(0.001, $this->anything());

        $connectionManager = new ClientConnectionManager($connector, $loop);

        $connectionManager->keepAlive(new Uri('https://reactphp.org/'), $connection);
    }

    public function testKeepAliveClosesConnectionAfterIdleTimeout()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('close');

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();

        $timerCallback = null;
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with($this->anything(), $this->callback(function ($cb) use (&$timerCallback) {
            $timerCallback = $cb;
            return true;
        }))->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $connectionManager = new ClientConnectionManager($connector, $loop);

        $connectionManager->keepAlive(new Uri('https://reactphp.org/'), $connection);

        // manually invoker timer function to emulate time has passed
        $this->assertNotNull($timerCallback);
        call_user_func($timerCallback); // $timerCallback() (PHP 5.4+)
    }

    public function testConnectUsesConnectorForNewConnectionWhenIdleConnectionFromPreviousKeepAliveCallHasAlreadyTimedOut()
    {
        $firstConnection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $firstConnection->expects($this->once())->method('close');

        $secondConnection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $secondConnection->expects($this->never())->method('close');

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('tls://reactphp.org:443')->willReturn(\React\Promise\resolve($secondConnection));

        $timerCallback = null;
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with($this->anything(), $this->callback(function ($cb) use (&$timerCallback) {
            $timerCallback = $cb;
            return true;
        }))->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $connectionManager = new ClientConnectionManager($connector, $loop);

        $connectionManager->keepAlive(new Uri('https://reactphp.org/'), $firstConnection);

        // manually invoker timer function to emulate time has passed
        $this->assertNotNull($timerCallback);
        call_user_func($timerCallback); // $timerCallback() (PHP 5.4+)

        $promise = $connectionManager->connect(new Uri('https://reactphp.org/'));
        assert($promise instanceof PromiseInterface);

        $connection = null;
        $promise->then(function ($value) use (&$connection) {
            $connection = $value;
        });

        $this->assertSame($secondConnection, $connection);
    }

    public function testConnectUsesConnectorForNewConnectionWhenIdleConnectionFromPreviousKeepAliveCallHasAlreadyFiredUnexpectedStreamEventBeforeIdleTimeoutThatClosesConnection()
    {
        $firstConnection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $firstConnection->expects($this->once())->method('close');

        $streamHandler = null;
        $firstConnection->expects($this->exactly(3))->method('on')->withConsecutive(
            array(
                'close',
                $this->callback(function ($cb) use (&$streamHandler) {
                    $streamHandler = $cb;
                    return true;
                })
            ),
            array(
                'data',
                $this->callback(function ($cb) use (&$streamHandler) {
                    assert($streamHandler instanceof \Closure);
                    return $cb === $streamHandler;
                })
            ),
            array(
                'error',
                $this->callback(function ($cb) use (&$streamHandler) {
                    assert($streamHandler instanceof \Closure);
                    return $cb === $streamHandler;
                })
            )
        );

        $secondConnection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $secondConnection->expects($this->never())->method('close');

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('tls://reactphp.org:443')->willReturn(\React\Promise\resolve($secondConnection));

        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->willReturn($timer);
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        $connectionManager = new ClientConnectionManager($connector, $loop);

        $connectionManager->keepAlive(new Uri('https://reactphp.org/'), $firstConnection);

        // manually invoke connection close to emulate server closing idle connection before idle timeout
        $this->assertNotNull($streamHandler);
        call_user_func($streamHandler); // $streamHandler() (PHP 5.4+)

        $promise = $connectionManager->connect(new Uri('https://reactphp.org/'));
        assert($promise instanceof PromiseInterface);

        $connection = null;
        $promise->then(function ($value) use (&$connection) {
            $connection = $value;
        });

        $this->assertSame($secondConnection, $connection);
    }
}
