<?php

namespace React\Tests\Http\Middleware;

use Clue\React\Block;
use React\EventLoop\Factory;
use React\Http\Middleware\Callback;
use React\Tests\Http\TestCase;

final class CallbackTest extends TestCase
{
    public function testRequest()
    {
        $request = $this
            ->getMockBuilder('Psr\Http\Message\ServerRequestInterface')
            ->getMock();
        $called = false;
        $callback = new Callback(function () use (&$called, $request) {
            $called = true;
            return $request;
        });
        $stack = $this
            ->getMockBuilder('React\Http\MiddlewareStackInterface')
            ->getMock();

        $stack
            ->expects($this->once())
            ->method('process')
            ->with($request)
            ->willReturn($request);

        $result = Block\await($callback->process($request, $stack), Factory::create());

        $this->assertSame($request, $result);
        $this->assertTrue($called);
    }

    public function testResponse()
    {
        $request = $this
            ->getMockBuilder('Psr\Http\Message\ServerRequestInterface')
            ->getMock();
        $response = $this
            ->getMockBuilder('Psr\Http\Message\ResponseInterface')
            ->getMock();
        $called = false;
        $callback = new Callback(function () use (&$called, $response) {
            $called = true;
            return $response;
        });
        $stack = $this
            ->getMockBuilder('React\Http\MiddlewareStackInterface')
            ->getMock();

        $stack
            ->expects($this->never())
            ->method('process')
            ->with($request);

        $result = Block\await($callback->process($request, $stack), Factory::create());

        $this->assertSame($response, $result);
        $this->assertTrue($called);
    }
}
