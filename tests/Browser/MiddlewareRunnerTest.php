<?php

namespace React\Tests\Http\Browser;

use PHPUnit\Framework\MockObject\MockObject;
use React\Http\Browser\MiddlewareRunner;
use React\Http\Io\Transaction;
use React\Http\Message\Request;
use React\Http\Message\ServerRequest;
use React\Tests\Http\BrowserMiddlewareStub;
use React\Tests\Http\TestCase;
use RingCentral\Psr7\Response;

final class MiddlewareRunnerTest extends TestCase
{
    public function testEmptyMiddlewareStack()
    {
        $request = new Request('GET', 'https://example.com/');

        /** @var Transaction&MockObject $transaction */
        $transaction = $this->createMock('\React\Http\Io\Transaction');
        $response = new Response();
        $transaction->expects(static::once())->method('send')->with($request)->willReturn($response);

        $middlewares = array();
        $middlewareStack = new MiddlewareRunner($middlewares, $transaction);

        $actualResponse = $middlewareStack($request);
        $this->assertSame($response, $actualResponse);
    }

    public function testWithMiddleware()
    {
        /** @var Transaction&MockObject $transaction */
        $transaction = $this->createMock('\React\Http\Io\Transaction');
        $transaction->expects(static::never())->method('send');

        $response = new Response();
        $middlewareStack = new MiddlewareRunner(array(
            new BrowserMiddlewareStub($response)
        ), $transaction);

        $request = new ServerRequest('GET', 'http://example.com/');

        $actualResponse = $middlewareStack($request);
        $this->assertSame($response, $actualResponse);
    }
}
