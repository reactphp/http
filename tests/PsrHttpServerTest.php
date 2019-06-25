<?php

namespace React\Tests\Http;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Http\PsrHttpServer;
use React\Http\Response;

final class PstHttpTest extends TestCase
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

    /**
     * @requires PHP 7.0
     */
    public function testSimpleRequestCallsRequestHandlerOnce()
    {
        /** @var RequstHandlerInterface $handler */
        $handler = new CallCountStubHandler(new Response());

        $server = new PsrHttpServer($handler, array());

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));
        $this->connection->emit('data', array("GET / HTTP/1.0\r\n\r\n"));

        self::assertSame(1, $handler->getCallCount());
    }

    /**
     * @requires PHP 7.0
     */
    public function testSimpleRequestCallsHandlerAndMiddlewares()
    {
        /** @var RequstHandlerInterface $handler */
        $handler = new CallCountStubHandler(new Response());

        $middlewareA = new CallCountStubMiddleware();
        $middlewareB = new CallCountStubMiddleware();

        $server = new PsrHttpServer($handler, array(
            $middlewareA,
            $middlewareB,
        ));

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));
        $this->connection->emit('data', array("GET / HTTP/1.0\r\n\r\n"));

        self::assertSame(1, $middlewareA->getCallCount());
        self::assertSame(1, $middlewareB->getCallCount());
        self::assertSame(1, $handler->getCallCount());
    }

    /**
     * @requires PHP 7.0
     *
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage All middlewares in the stack must implement the Psr\Http\Server\MiddlewareInterface.
     */
    public function testConstructorThrowsExceptionWhenInvalidMiddlewareIsPassed()
    {
        /** @var RequstHandlerInterface $handler */
        $handler = new CallCountStubHandler(new Response());

        new PsrHttpServer($handler, array('handle'));
    }

    /**
     * @requires PHP 7.0
     */
    public function testForwardErrors()
    {
        $exception = new \Exception();

        /** @var RequstHandlerInterface $handler */
        $handler = new CallCountStubHandler($exception);

        $capturedException = null;

        $server = new PsrHttpServer($handler, array());

        $server->on('error', function ($error) use (&$capturedException) {
            $capturedException = $error;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $this->connection->emit('data', array("GET / HTTP/1.0\r\n\r\n"));

        $this->assertInstanceOf('RuntimeException', $capturedException);
        $this->assertInstanceOf('Exception', $capturedException->getPrevious());
        $this->assertSame($exception, $capturedException->getPrevious());
    }
}
