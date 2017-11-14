<?php

namespace React\Tests\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\MiddlewareRunner;
use React\Http\ServerRequest;
use React\Tests\Http\Middleware\ProcessStack;
use RingCentral\Psr7\Response;
use Clue\React\Block;

final class MiddlewareRunnerTest extends TestCase
{
    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage No middleware to run
     */
    public function testDefaultResponse()
    {
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

    public function testNextCanBeRunMoreThanOnceWithoutCorruptingTheMiddlewareStack()
    {
        $exception = new \RuntimeException('exception');
        $retryCalled = 0;
        $error = null;
        $retry = function ($request, $next) use (&$error, &$retryCalled) {
            return $next($request)->then(null, function ($et) use (&$error, $request, $next, &$retryCalled) {
                $retryCalled++;
                $error = $et;
                // the $next failed. discard $error and retry once again:
                return $next($request);
            });
        };

        $response = new Response();
        $called = 0;
        $runner = new MiddlewareRunner(array(
        $retry,
        function () use (&$called, $response, $exception) {
            $called++;
            if ($called === 1) {
                throw $exception;
            }

            return $response;
        }
        ));

        $request = new ServerRequest('GET', 'https://example.com/');

        $this->assertSame($response, Block\await($runner($request), Factory::create()));
        $this->assertSame(1, $retryCalled);
        $this->assertSame(2, $called);
        $this->assertSame($exception, $error);
    }

    public function testMultipleRunsInvokeAllMiddlewareInCorrectOrder()
    {
        $requests = array(
            new ServerRequest('GET', 'https://example.com/1'),
            new ServerRequest('GET', 'https://example.com/2'),
            new ServerRequest('GET', 'https://example.com/3')
        );

        $receivedRequests = array();

        $middlewareRunner = new MiddlewareRunner(array(
            function (ServerRequestInterface $request, $next) use (&$receivedRequests) {
                $receivedRequests[] = 'middleware1: ' . $request->getUri();
                return $next($request);
            },
            function (ServerRequestInterface $request, $next) use (&$receivedRequests) {
                $receivedRequests[] = 'middleware2: ' . $request->getUri();
                return $next($request);
            },
            function (ServerRequestInterface $request, $next) use (&$receivedRequests) {
                $receivedRequests[] = 'middleware3: ' . $request->getUri();
                return $next($request);
            }
        ));

        foreach ($requests as $request) {
            $middlewareRunner($request);
        }

        $this->assertEquals(
            array(
                'middleware1: https://example.com/1',
                'middleware2: https://example.com/1',
                'middleware3: https://example.com/1',
                'middleware1: https://example.com/2',
                'middleware2: https://example.com/2',
                'middleware3: https://example.com/2',
                'middleware1: https://example.com/3',
                'middleware2: https://example.com/3',
                'middleware3: https://example.com/3'
            ),
            $receivedRequests
        );
    }
}
