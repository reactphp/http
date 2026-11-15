<?php

namespace React\Tests\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Middleware\StreamingRequestMiddleware;
use React\Promise\Promise;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\SocketServer;
use React\Stream\ThroughStream;
use function React\Async\await;
use function React\Promise\all;
use function React\Promise\Stream\buffer;
use function React\Promise\Stream\first;
use function React\Promise\Timer\sleep;
use function React\Promise\Timer\timeout;

class FunctionalHttpServerTest extends TestCase
{
    public function testPlainHttpOnRandomPort()
    {
        $connector = new Connector();

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, [], (string)$request->getUri());
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringContainsString("HTTP/1.0 200 OK", $response);
        $this->assertStringContainsString('http://' . noScheme($socket->getAddress()) . '/', $response);

        $socket->close();
    }

    public function testPlainHttpOnRandomPortWithSingleRequestHandlerArray()
    {
        $connector = new Connector();

        $http = new HttpServer(
            function () {
                return new Response(404);
            }
        );

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringContainsString("HTTP/1.0 404 Not Found", $response);

        $socket->close();
    }

    public function testPlainHttpOnRandomPortWithoutHostHeaderUsesSocketUri()
    {
        $connector = new Connector();

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, [], (string)$request->getUri());
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringContainsString("HTTP/1.0 200 OK", $response);
        $this->assertStringContainsString('http://' . noScheme($socket->getAddress()) . '/', $response);

        $socket->close();
    }

    public function testPlainHttpOnRandomPortWithOtherHostHeaderTakesPrecedence()
    {
        $connector = new Connector();

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, [], (string)$request->getUri());
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: localhost:1000\r\n\r\n");

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringContainsString("HTTP/1.0 200 OK", $response);
        $this->assertStringContainsString('http://localhost:1000/', $response);

        $socket->close();
    }

    public function testSecureHttpsOnRandomPort()
    {
        $connector = new Connector([
            'tls' => [
                'verify_peer' => false
            ]
        ]);

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, [], (string)$request->getUri());
        });

        $socket = new SocketServer('tls://127.0.0.1:0', ['tls' => [
            'local_cert' => __DIR__ . '/../examples/localhost.pem'
        ]]);
        $http->listen($socket);

        // clear OpenSSL errors from closing the server side before the client reads,
        // as legacy PHP < 7.2 does not (https://bugs.php.net/bug.php?id=78775)
        $socket->on('connection', function (ConnectionInterface $conn) {
            $conn->on('close', function () {
                Loop::futureTick(function () {
                    while (openssl_error_string() !== false);
                });
            });
        });

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringContainsString("HTTP/1.0 200 OK", $response);
        $this->assertStringContainsString('https://' . noScheme($socket->getAddress()) . '/', $response);

        $socket->close();
    }

    public function testSecureHttpsReturnsData()
    {
        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(
                200,
                [],
                str_repeat('.', 33000)
            );
        });

        $socket = new SocketServer('tls://127.0.0.1:0', ['tls' => ['local_cert' => __DIR__ . '/../examples/localhost.pem']]);
        $http->listen($socket);

        // clear OpenSSL errors from closing the server side before the client reads,
        // as legacy PHP < 7.2 does not (https://bugs.php.net/bug.php?id=78775)
        $socket->on('connection', function (ConnectionInterface $conn) {
            $conn->on('close', function () {
                Loop::futureTick(function () {
                    while (openssl_error_string() !== false);
                });
            });
        });

        $connector = new Connector(['tls' => [
            'verify_peer' => false
        ]]);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringContainsString("HTTP/1.0 200 OK", $response);
        $this->assertStringContainsString("\r\nContent-Length: 33000\r\n", $response);
        $this->assertStringEndsWith("\r\n". str_repeat('.', 33000), $response);

        $socket->close();
    }

    public function testSecureHttpsOnRandomPortWithoutHostHeaderUsesSocketUri()
    {
        $connector = new Connector([
            'tls' => ['verify_peer' => false]
        ]);

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, [], (string)$request->getUri());
        });

        $socket = new SocketServer('tls://127.0.0.1:0', ['tls' => [
            'local_cert' => __DIR__ . '/../examples/localhost.pem'
        ]]);
        $http->listen($socket);

        // clear OpenSSL errors from closing the server side before the client reads,
        // as legacy PHP < 7.2 does not (https://bugs.php.net/bug.php?id=78775)
        $socket->on('connection', function (ConnectionInterface $conn) {
            $conn->on('close', function () {
                Loop::futureTick(function () {
                    while (openssl_error_string() !== false);
                });
            });
        });

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringContainsString("HTTP/1.0 200 OK", $response);
        $this->assertStringContainsString('https://' . noScheme($socket->getAddress()) . '/', $response);

        $socket->close();
    }

    public function testPlainHttpOnStandardPortReturnsUriWithNoPort()
    {
        try {
            $socket = new SocketServer('127.0.0.1:80');
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Listening on port 80 failed (root and unused?)');
        }
        $connector = new Connector();

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, [], (string)$request->getUri());
        });

        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: 127.0.0.1\r\n\r\n");

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringContainsString("HTTP/1.0 200 OK", $response);
        $this->assertStringContainsString('http://127.0.0.1/', $response);

        $socket->close();
    }

    public function testPlainHttpOnStandardPortWithoutHostHeaderReturnsUriWithNoPort()
    {
        try {
            $socket = new SocketServer('127.0.0.1:80');
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Listening on port 80 failed (root and unused?)');
        }
        $connector = new Connector();

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, [], (string)$request->getUri());
        });

        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringContainsString("HTTP/1.0 200 OK", $response);
        $this->assertStringContainsString('http://127.0.0.1/', $response);

        $socket->close();
    }

    public function testSecureHttpsOnStandardPortReturnsUriWithNoPort()
    {
        try {
            $socket = new SocketServer('tls://127.0.0.1:443', ['tls' => [
                'local_cert' => __DIR__ . '/../examples/localhost.pem'
            ]]);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Listening on port 443 failed (root and unused?)');
        }

        $connector = new Connector([
            'tls' => ['verify_peer' => false]
        ]);

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, [], (string)$request->getUri());
        });

        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: 127.0.0.1\r\n\r\n");

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringContainsString("HTTP/1.0 200 OK", $response);
        $this->assertStringContainsString('https://127.0.0.1/', $response);

        $socket->close();
    }

    public function testSecureHttpsOnStandardPortWithoutHostHeaderUsesSocketUri()
    {
        try {
            $socket = new SocketServer('tls://127.0.0.1:443', ['tls' => [
                'local_cert' => __DIR__ . '/../examples/localhost.pem'
            ]]);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Listening on port 443 failed (root and unused?)');
        }

        $connector = new Connector([
            'tls' => ['verify_peer' => false]
        ]);

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, [], (string)$request->getUri());
        });

        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringContainsString("HTTP/1.0 200 OK", $response);
        $this->assertStringContainsString('https://127.0.0.1/', $response);

        $socket->close();
    }

    public function testPlainHttpOnHttpsStandardPortReturnsUriWithPort()
    {
        try {
            $socket = new SocketServer('127.0.0.1:443');
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Listening on port 443 failed (root and unused?)');
        }
        $connector = new Connector();

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, [], (string)$request->getUri());
        });

        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringContainsString("HTTP/1.0 200 OK", $response);
        $this->assertStringContainsString('http://127.0.0.1:443/', $response);

        $socket->close();
    }

    public function testSecureHttpsOnHttpStandardPortReturnsUriWithPort()
    {
        try {
            $socket = new SocketServer('tls://127.0.0.1:80', ['tls' => [
                'local_cert' => __DIR__ . '/../examples/localhost.pem'
            ]]);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Listening on port 80 failed (root and unused?)');
        }

        $connector = new Connector([
            'tls' => ['verify_peer' => false]
        ]);

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, [], (string)$request->getUri() . 'x' . $request->getHeaderLine('Host'));
        });

        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringContainsString("HTTP/1.0 200 OK", $response);
        $this->assertStringContainsString('https://127.0.0.1:80/', $response);

        $socket->close();
    }

    public function testClosedStreamFromRequestHandlerWillSendEmptyBody()
    {
        $connector = new Connector();

        $stream = new ThroughStream();
        $stream->close();

        $http = new HttpServer(function (RequestInterface $request) use ($stream) {
            return new Response(200, [], $stream);
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringStartsWith("HTTP/1.0 200 OK", $response);
        $this->assertStringEndsWith("\r\n\r\n", $response);

        $socket->close();
    }

    public function testRequestHandlerWithStreamingRequestWillReceiveCloseEventIfConnectionClosesWhileSendingBody()
    {
        $connector = new Connector();

        $once = $this->expectCallableOnce();
        $http = new HttpServer(
            new StreamingRequestMiddleware(),
            function (RequestInterface $request) use ($once) {
                $request->getBody()->on('close', $once);
            }
        );

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nContent-Length: 100\r\n\r\n");

            Loop::addTimer(0.001, function() use ($conn) {
                $conn->end();
            });
        });

        await(sleep(0.1));

        $socket->close();
    }

    public function testStreamFromRequestHandlerWillBeClosedIfConnectionClosesWhileSendingStreamingRequestBody()
    {
        $connector = new Connector();

        $stream = new ThroughStream();

        $http = new HttpServer(
            new StreamingRequestMiddleware(),
            function (RequestInterface $request) use ($stream) {
                return new Response(200, [], $stream);
            }
        );

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nContent-Length: 100\r\n\r\n");

            Loop::addTimer(0.001, function() use ($conn) {
                $conn->end();
            });
        });

        // stream will be closed within 0.1s
        $ret = await(timeout(first($stream, 'close'), 0.1));

        $socket->close();

        $this->assertNull($ret);
    }

    public function testStreamFromRequestHandlerWillBeClosedIfConnectionCloses()
    {
        $connector = new Connector();

        $stream = new ThroughStream();

        $http = new HttpServer(function (RequestInterface $request) use ($stream) {
            return new Response(200, [], $stream);
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            Loop::addTimer(0.1, function () use ($conn) {
                $conn->close();
            });
        });

        // await response stream to be closed
        $ret = await(timeout(first($stream, 'close'), 1.0));

        $socket->close();

        $this->assertNull($ret);
    }

    public function testUpgradeWithThroughStreamReturnsDataAsGiven()
    {
        $connector = new Connector();

        $http = new HttpServer(function (RequestInterface $request) {
            $stream = new ThroughStream();

            Loop::addTimer(0.1, function () use ($stream) {
                $stream->end();
            });

            return new Response(101, ['Upgrade' => 'echo'], $stream);
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.1\r\nHost: example.com:80\r\nUpgrade: echo\r\n\r\n");

            $conn->once('data', function () use ($conn) {
                $conn->write('hello');
                $conn->write('world');
            });

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringStartsWith("HTTP/1.1 101 Switching Protocols\r\n", $response);
        $this->assertStringEndsWith("\r\n\r\nhelloworld", $response);

        $socket->close();
    }

    public function testUpgradeWithRequestBodyAndThroughStreamReturnsDataAsGiven()
    {
        $connector = new Connector();

        $http = new HttpServer(function (RequestInterface $request) {
            $stream = new ThroughStream();

            Loop::addTimer(0.1, function () use ($stream) {
                $stream->end();
            });

            return new Response(101, ['Upgrade' => 'echo'], $stream);
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("POST / HTTP/1.1\r\nHost: example.com:80\r\nUpgrade: echo\r\nContent-Length: 3\r\n\r\n");
            $conn->write('hoh');

            $conn->once('data', function () use ($conn) {
                $conn->write('hello');
                $conn->write('world');
            });

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringStartsWith("HTTP/1.1 101 Switching Protocols\r\n", $response);
        $this->assertStringEndsWith("\r\n\r\nhelloworld", $response);

        $socket->close();
    }

    public function testConnectWithThroughStreamReturnsDataAsGiven()
    {
        $connector = new Connector();

        $http = new HttpServer(function (RequestInterface $request) {
            $stream = new ThroughStream();

            Loop::addTimer(0.1, function () use ($stream) {
                $stream->end();
            });

            return new Response(200, [], $stream);
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\nConnection: close\r\n\r\n");

            $conn->once('data', function () use ($conn) {
                $conn->write('hello');
                $conn->write('world');
            });

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertStringEndsWith("\r\n\r\nhelloworld", $response);

        $socket->close();
    }

    public function testConnectWithThroughStreamReturnedFromPromiseReturnsDataAsGiven()
    {
        $connector = new Connector();

        $http = new HttpServer(function (RequestInterface $request) {
            $stream = new ThroughStream();

            Loop::addTimer(0.1, function () use ($stream) {
                $stream->end();
            });

            return new Promise(function ($resolve) use ($stream) {
                Loop::addTimer(0.001, function () use ($resolve, $stream) {
                    $resolve(new Response(200, [], $stream));
                });
            });
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\nConnection: close\r\n\r\n");

            $conn->once('data', function () use ($conn) {
                $conn->write('hello');
                $conn->write('world');
            });

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertStringEndsWith("\r\n\r\nhelloworld", $response);

        $socket->close();
    }

    public function testConnectWithClosedThroughStreamReturnsNoData()
    {
        $connector = new Connector();

        $http = new HttpServer(function (RequestInterface $request) {
            $stream = new ThroughStream();
            $stream->close();

            return new Response(200, [], $stream);
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\nConnection: close\r\n\r\n");

            $conn->once('data', function () use ($conn) {
                $conn->write('hello');
                $conn->write('world');
            });

            return buffer($conn);
        });

        $response = await(timeout($result, 1.0));

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertStringEndsWith("\r\n\r\n", $response);

        $socket->close();
    }

    public function testLimitConcurrentRequestsMiddlewareRequestStreamPausing()
    {
        $connector = new Connector();

        $http = new HttpServer(
            new LimitConcurrentRequestsMiddleware(5),
            new RequestBodyBufferMiddleware(16 * 1024 * 1024), // 16 MiB
            function (ServerRequestInterface $request, $next) {
                return new Promise(function ($resolve) use ($request, $next) {
                    Loop::addTimer(0.1, function () use ($request, $resolve, $next) {
                        $resolve($next($request));
                    });
                });
            },
            function (ServerRequestInterface $request) {
                return new Response(200, [], (string)strlen((string)$request->getBody()));
            }
        );

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = [];
        for ($i = 0; $i < 6; $i++) {
            $result[] = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
                $conn->write(
                    "GET / HTTP/1.0\r\nContent-Length: 1024\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n" .
                    str_repeat('a', 1024) .
                    "\r\n\r\n"
                );

                return buffer($conn);
            });
        }

        $responses = await(timeout(all($result), 1.0));

        foreach ($responses as $response) {
            $this->assertStringContainsString("HTTP/1.0 200 OK", $response, $response);
            $this->assertTrue(substr($response, -4) == 1024, $response);
        }

        $socket->close();
    }

}

function noScheme($uri)
{
    $pos = strpos($uri, '://');
    if ($pos !== false) {
        $uri = substr($uri, $pos + 3);
    }
    return $uri;
}
