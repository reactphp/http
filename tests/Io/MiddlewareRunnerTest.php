<?php

namespace React\Tests\Http\Io;

use Clue\React\Block;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Io\MiddlewareRunner;
use React\Http\Io\ServerRequest;
use React\Promise;
use React\Tests\Http\Middleware\ProcessStack;
use React\Tests\Http\TestCase;
use RingCentral\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;

final class MiddlewareRunnerTest extends TestCase
{
    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage No middleware to run
     */
    public function testEmptyMiddlewareStackThrowsException()
    {
        $request = new ServerRequest('GET', 'https://example.com/');
        $middlewares = array();
        $middlewareStack = new MiddlewareRunner($middlewares);

        $middlewareStack($request);
    }

    public function testMiddlewareHandlerReceivesTwoArguments()
    {
        $args = null;
        $middleware = new MiddlewareRunner(array(
            function (ServerRequestInterface $request, $next) use (&$args) {
                $args = func_num_args();
                return $next($request);
            },
            function (ServerRequestInterface $request) {
                return null;
            }
        ));

        $request = new ServerRequest('GET', 'http://example.com/');

        $middleware($request);

        $this->assertEquals(2, $args);
    }

    public function testFinalHandlerReceivesOneArgument()
    {
        $args = null;
        $middleware = new MiddlewareRunner(array(
            function (ServerRequestInterface $request) use (&$args) {
                $args = func_num_args();
                return null;
            }
        ));

        $request = new ServerRequest('GET', 'http://example.com/');

        $middleware($request);

        $this->assertEquals(1, $args);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage hello
     */
    public function testThrowsIfHandlerThrowsException()
    {
        $middleware = new MiddlewareRunner(array(
            function (ServerRequestInterface $request) {
                throw new \RuntimeException('hello');
            }
        ));

        $request = new ServerRequest('GET', 'http://example.com/');

        $middleware($request);
    }

    /**
     * @requires PHP 7
     * @expectedException Throwable
     * @expectedExceptionMessage hello
     */
    public function testThrowsIfHandlerThrowsThrowable()
    {
        $middleware = new MiddlewareRunner(array(
            function (ServerRequestInterface $request) {
                throw new \Error('hello');
            }
        ));

        $request = new ServerRequest('GET', 'http://example.com/');

        $middleware($request);
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
        // the ProcessStack middleware instances are stateful, so reset these
        // before running the test, to not fail with --repeat=100
        foreach ($middlewares as $middleware) {
            if ($middleware instanceof ProcessStack) {
                $middleware->reset();
            }
        }

        $request = new ServerRequest('GET', 'https://example.com/');
        $middlewareStack = new MiddlewareRunner($middlewares);

        $response = $middlewareStack($request);

        $this->assertTrue($response instanceof PromiseInterface);
        $response = Block\await($response, Factory::create());

        $this->assertTrue($response instanceof ResponseInterface);
        $this->assertSame(200, $response->getStatusCode());

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
            $promise = new \React\Promise\Promise(function ($resolve) use ($request, $next) {
                $resolve($next($request));
            });

            return $promise->then(null, function ($et) use (&$error, $request, $next, &$retryCalled) {
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
            function (ServerRequestInterface $request) use (&$receivedRequests) {
                $receivedRequests[] = 'middleware3: ' . $request->getUri();
                return new \React\Promise\Promise(function () { });
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

        $response = $middlewareStack($request);

        $this->assertTrue($response instanceof ResponseInterface);
        $this->assertSame($expectedSequence, (string) $response->getBody());
    }

    public function testPendingNextRequestHandlersCanBeCalledConcurrently()
    {
        $called = 0;
        $middleware = new MiddlewareRunner(array(
            function (RequestInterface $request, $next) {
                $first = $next($request);
                $second = $next($request);

                return new Response();
            },
            function (RequestInterface $request) use (&$called) {
                ++$called;

                return new Promise\Promise(function () { });
            }
        ));

        $request = new ServerRequest('GET', 'http://example.com/');

        $response = $middleware($request);

        $this->assertTrue($response instanceof ResponseInterface);
        $this->assertEquals(2, $called);
    }

    public function testCancelPendingNextHandler()
    {
        $once = $this->expectCallableOnce();
        $middleware = new MiddlewareRunner(array(
            function (RequestInterface $request, $next) {
                $ret = $next($request);
                $ret->cancel();

                return $ret;
            },
            function (RequestInterface $request) use ($once) {
                return new Promise\Promise(function () { }, $once);
            }
        ));

        $request = new ServerRequest('GET', 'http://example.com/');

        $middleware($request);
    }

    public function testCancelResultingPromiseWillCancelPendingNextHandler()
    {
        $once = $this->expectCallableOnce();
        $middleware = new MiddlewareRunner(array(
            function (RequestInterface $request, $next) {
                return $next($request);
            },
            function (RequestInterface $request) use ($once) {
                return new Promise\Promise(function () { }, $once);
            }
        ));

        $request = new ServerRequest('GET', 'http://example.com/');

        $promise = $middleware($request);

        $this->assertTrue($promise instanceof CancellablePromiseInterface);
        $promise->cancel();
    }
}
