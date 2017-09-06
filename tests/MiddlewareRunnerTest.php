<?php

namespace React\Tests\Http;

use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Factory;
use React\Http\MiddlewareRunner;
use React\Http\ServerRequest;
use React\Tests\Http\Middleware\ProcessStack;
use RingCentral\Psr7\Response;
use Clue\React\Block;

final class MiddlewareRunnerTest extends TestCase
{
    public function testDefaultResponse()
    {
        $this->setExpectedException('\RuntimeException');
        $request = new ServerRequest('GET', 'https://example.com/');
        $middlewares = array();
        $middlewareStack = new MiddlewareRunner($middlewares);

        Block\await($middlewareStack($request), Factory::create());
    }

    public function provideProcessStackMiddlewares()
    {
        $processStackA = new ProcessStack();
        $processStackB = new ProcessStack();
        $processStackC = new ProcessStack();
        $processStackD = new ProcessStack();
        $responseMiddleware = function () {
            return new Response(200);
        };
        return array(
            array(
                array(
                    $processStackA,
                    $responseMiddleware,
                ),
                1,
            ),
            array(
                array(
                    $processStackB,
                    $processStackB,
                    $responseMiddleware,
                ),
                2,
            ),
            array(
                array(
                    $processStackC,
                    $processStackC,
                    $processStackC,
                    $responseMiddleware,
                ),
                3,
            ),
            array(
                array(
                    $processStackD,
                    $processStackD,
                    $processStackD,
                    $processStackD,
                    $responseMiddleware,
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
        $middlewareStack = new MiddlewareRunner($middlewares);

        /** @var ResponseInterface $result */
        $result = Block\await($middlewareStack($request), Factory::create());
        $this->assertSame(200, $result->getStatusCode());
        foreach ($middlewares as $middleware) {
            if (!($middleware instanceof ProcessStack)) {
                continue;
            }

            $this->assertSame($expectedCallCount, $middleware->getCallCount());
        }
    }
}
