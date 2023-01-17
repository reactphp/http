<?php

namespace React\Tests\Http\Io;

use RingCentral\Psr7\Uri;
use React\Http\Io\ClientConnectionManager;
use React\Promise\Promise;
use React\Tests\Http\TestCase;

class ClientConnectionManagerTest extends TestCase
{
    public function testConnectWithHttpsUriShouldConnectToTlsWithDefaultPort()
    {
        $promise = new Promise(function () { });
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('tls://reactphp.org:443')->willReturn($promise);

        $connectionManager = new ClientConnectionManager($connector);

        $ret = $connectionManager->connect(new Uri('https://reactphp.org/'));
        $this->assertSame($promise, $ret);
    }

    public function testConnectWithHttpUriShouldConnectToTcpWithDefaultPort()
    {
        $promise = new Promise(function () { });
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('reactphp.org:80')->willReturn($promise);

        $connectionManager = new ClientConnectionManager($connector);

        $ret = $connectionManager->connect(new Uri('http://reactphp.org/'));
        $this->assertSame($promise, $ret);
    }

    public function testConnectWithExplicitPortShouldConnectWithGivenPort()
    {
        $promise = new Promise(function () { });
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('reactphp.org:8080')->willReturn($promise);

        $connectionManager = new ClientConnectionManager($connector);

        $ret = $connectionManager->connect(new Uri('http://reactphp.org:8080/'));
        $this->assertSame($promise, $ret);
    }

    public function testConnectWithInvalidSchemeShouldRejectWithException()
    {
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->never())->method('connect');

        $connectionManager = new ClientConnectionManager($connector);

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

        $connectionManager = new ClientConnectionManager($connector);

        $promise = $connectionManager->connect(new Uri('reactphp.org'));

        $exception = null;
        $promise->then(null, function ($reason) use (&$exception) {
            $exception = $reason;
        });

        $this->assertInstanceOf('InvalidArgumentException', $exception);
        $this->assertEquals('Invalid request URL given', $exception->getMessage());
    }

    public function testHandBackWillCloseGivenConnectionUntilKeepAliveIsActuallySupported()
    {
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();

        $connectionManager = new ClientConnectionManager($connector);

        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('close');

        $connectionManager->handBack($connection);
    }
}
