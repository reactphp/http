<?php

namespace React\Tests\Http;

use Clue\React\Block;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Io\ServerRequest;
use React\Http\MiddlewareRunner;
use React\Promise;
use React\Tests\Http\Middleware\ProcessStack;
use RingCentral\Psr7\Response;

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

    public function provideErrorHandler()
    {
        return array(
            array(
                function (\Exception $e) {
                    throw $e;
                }
            ),
            array(
                function (\Exception $e) {
                    return Promise\reject($e);
                }
            )
        );
    }

    /**
     * @dataProvider provideErrorHandler
     */
    public function testNextCanBeRunMoreThanOnceWithoutCorruptingTheMiddlewareStack($errorHandler)
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
            function () use ($errorHandler, &$called, $response, $exception) {
                $called++;
                if ($called === 1) {
                    return $errorHandler($exception);
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

    public function provideUncommonMiddlewareArrayFormats()
    {
        return array(
            array(
                function () {
                    $sequence = '';

                    // Numeric index gap
                    return array(
                        0 => function (ServerRequestInterface $request, $next) use (&$sequence) {
                            $sequence .= 'A';

                            return $next($request);
                        },
                        2 => function (ServerRequestInterface $request, $next) use (&$sequence) {
                            $sequence .= 'B';

                            return $next($request);
                        },
                        3 => function () use (&$sequence) {
                            return new Response(200, array(), $sequence . 'C');
                        },
                    );
                },
                'ABC',
            ),
            array(
                function () {
                    $sequence = '';

                    // Reversed numeric indexes
                    return array(
                        2 => function (ServerRequestInterface $request, $next) use (&$sequence) {
                            $sequence .= 'A';

                            return $next($request);
                        },
                        1 => function (ServerRequestInterface $request, $next) use (&$sequence) {
                            $sequence .= 'B';

                            return $next($request);
                        },
                        0 => function () use (&$sequence) {
                            return new Response(200, array(), $sequence . 'C');
                        },
                    );
                },
                'ABC',
            ),
            array(
                function () {
                    $sequence = '';

                    // Associative array
                    return array(
                        'middleware1' => function (ServerRequestInterface $request, $next) use (&$sequence) {
                            $sequence .= 'A';

                            return $next($request);
                        },
                        'middleware2' => function (ServerRequestInterface $request, $next) use (&$sequence) {
                            $sequence .= 'B';

                            return $next($request);
                        },
                        'middleware3' => function () use (&$sequence) {
                            return new Response(200, array(), $sequence . 'C');
                        },
                    );
                },
                'ABC',
            ),
            array(
                function () {
                    $sequence = '';

                    // Associative array with empty or trimmable string keys
                    return array(
                        '' => function (ServerRequestInterface $request, $next) use (&$sequence) {
                            $sequence .= 'A';

                            return $next($request);
                        },
                        ' ' => function (ServerRequestInterface $request, $next) use (&$sequence) {
                            $sequence .= 'B';

                            return $next($request);
                        },
                        '  ' => function () use (&$sequence) {
                            return new Response(200, array(), $sequence . 'C');
                        },
                    );
                },
                'ABC',
            ),
            array(
                function () {
                    $sequence = '';

                    // Mixed array keys
                    return array(
                        '' => function (ServerRequestInterface $request, $next) use (&$sequence) {
                            $sequence .= 'A';

                            return $next($request);
                        },
                        0 => function (ServerRequestInterface $request, $next) use (&$sequence) {
                            $sequence .= 'B';

                            return $next($request);
                        },
                        'foo' => function (ServerRequestInterface $request, $next) use (&$sequence) {
                            $sequence .= 'C';

                            return $next($request);
                        },
                        2 => function () use (&$sequence) {
                            return new Response(200, array(), $sequence . 'D');
                        },
                    );
                },
                'ABCD',
            ),
        );
    }

    /**
     * @dataProvider provideUncommonMiddlewareArrayFormats
     */
    public function testUncommonMiddlewareArrayFormats($middlewareFactory, $expectedSequence)
    {
        $request = new ServerRequest('GET', 'https://example.com/');
        $middlewareStack = new MiddlewareRunner($middlewareFactory());

        /** @var ResponseInterface $response */
        $response = Block\await($middlewareStack($request), Factory::create());

        $this->assertSame($expectedSequence, (string) $response->getBody());
    }
}
