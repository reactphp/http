<?php

namespace React\Tests\Http;

use React\EventLoop\Factory;
use React\Http\MiddlewareStack;
use React\Http\ServerRequest;
use React\Tests\Http\Middleware\ProcessStack;
use RingCentral\Psr7\Response;
use Clue\React\Block;

final class MiddlewareStackTest extends TestCase
{
    public function testDefaultResponse()
    {
        $request = new ServerRequest('GET', 'https://example.com/');
        $defaultResponse = new Response(404);
        $middlewares = array();
        $middlewareStack = new MiddlewareStack($defaultResponse, $middlewares);

        $result = Block\await($middlewareStack($request), Factory::create());
        $this->assertSame($defaultResponse, $result);
    }

    public function provideProcessStackMiddlewares()
    {
        $processStackA = new ProcessStack();
        $processStackB = new ProcessStack();
        $processStackC = new ProcessStack();
        $processStackD = new ProcessStack();
        return array(
            array(
                array(
                    $processStackA,
                ),
                1,
            ),
            array(
                array(
                    $processStackB,
                    $processStackB,
                ),
                2,
            ),
            array(
                array(
                    $processStackC,
                    $processStackC,
                    $processStackC,
                ),
                3,
            ),
            array(
                array(
                    $processStackD,
                    $processStackD,
                    $processStackD,
                    $processStackD,
                ),
                4,
            ),
        );
    }

    /**
     * @dataProvider provideProcessStackMiddlewares
     */
    public function testProcessStack(array $middlewares, $expectedCallCount)
    {
        $request = new ServerRequest('GET', 'https://example.com/');
        $defaultResponse = new Response(404);
        $middlewareStack = new MiddlewareStack($defaultResponse, $middlewares);

        $result = Block\await($middlewareStack($request), Factory::create());
        $this->assertSame($defaultResponse, $result);
        foreach ($middlewares as $middleware) {
            $this->assertSame($expectedCallCount, $middleware->getCallCount());
        }
    }
}
