<?php

namespace React\Tests\Http;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Response;
use React\Http\StreamingServer;
use React\Socket\Server as Socket;
use React\EventLoop\Factory;
use Psr\Http\Message\RequestInterface;
use React\Socket\Connector;
use React\Socket\ConnectionInterface;
use Clue\React\Block;
use React\Socket\SecureServer;
use React\Promise;
use React\Promise\Stream;
use React\Stream\ThroughStream;

class FunctionalServerTest extends TestCase
{
    public function testPlainHttpOnRandomPort()
    {
        $loop = Factory::create();
        $connector = new Connector($loop);

        $server = new StreamingServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $socket = new Socket(0, $loop);
        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('http://' . noScheme($socket->getAddress()) . '/', $response);

        $socket->close();
    }

    public function testPlainHttpOnRandomPortWithSingleRequestHandlerArray()
    {
        $loop = Factory::create();
        $connector = new Connector($loop);

        $server = new StreamingServer(array(
            function () {
                return new Response(404);
            },
        ));

        $socket = new Socket(0, $loop);
        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 404 Not Found", $response);

        $socket->close();
    }

    public function testPlainHttpOnRandomPortWithoutHostHeaderUsesSocketUri()
    {
        $loop = Factory::create();
        $connector = new Connector($loop);

        $server = new StreamingServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $socket = new Socket(0, $loop);
        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('http://' . noScheme($socket->getAddress()) . '/', $response);

        $socket->close();
    }

    public function testPlainHttpOnRandomPortWithOtherHostHeaderTakesPrecedence()
    {
        $loop = Factory::create();
        $connector = new Connector($loop);

        $server = new StreamingServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $socket = new Socket(0, $loop);
        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: localhost:1000\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('http://localhost:1000/', $response);

        $socket->close();
    }

    public function testSecureHttpsOnRandomPort()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        $loop = Factory::create();
        $connector = new Connector($loop, array(
            'tls' => array('verify_peer' => false)
        ));

        $server = new StreamingServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $socket = new Socket(0, $loop);
        $socket = new SecureServer($socket, $loop, array(
            'local_cert' => __DIR__ . '/../examples/localhost.pem'
        ));
        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('https://' . noScheme($socket->getAddress()) . '/', $response);

        $socket->close();
    }

    public function testSecureHttpsReturnsData()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        $loop = Factory::create();

        $server = new StreamingServer(function (RequestInterface $request) {
            return new Response(
                200,
                array(),
                str_repeat('.', 33000)
            );
        });

        $socket = new Socket(0, $loop);
        $socket = new SecureServer($socket, $loop, array(
            'local_cert' => __DIR__ . '/../examples/localhost.pem'
        ));
        $server->listen($socket);

        $connector = new Connector($loop, array(
            'tls' => array('verify_peer' => false)
        ));

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains("\r\nContent-Length: 33000\r\n", $response);
        $this->assertStringEndsWith("\r\n". str_repeat('.', 33000), $response);

        $socket->close();
    }

    public function testSecureHttpsOnRandomPortWithoutHostHeaderUsesSocketUri()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        $loop = Factory::create();
        $connector = new Connector($loop, array(
            'tls' => array('verify_peer' => false)
        ));

        $server = new StreamingServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $socket = new Socket(0, $loop);
        $socket = new SecureServer($socket, $loop, array(
            'local_cert' => __DIR__ . '/../examples/localhost.pem'
        ));
        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('https://' . noScheme($socket->getAddress()) . '/', $response);

        $socket->close();
    }

    public function testPlainHttpOnStandardPortReturnsUriWithNoPort()
    {
        $loop = Factory::create();
        try {
            $socket = new Socket(80, $loop);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Listening on port 80 failed (root and unused?)');
        }
        $connector = new Connector($loop);

        $server = new StreamingServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: 127.0.0.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('http://127.0.0.1/', $response);

        $socket->close();
    }

    public function testPlainHttpOnStandardPortWithoutHostHeaderReturnsUriWithNoPort()
    {
        $loop = Factory::create();
        try {
            $socket = new Socket(80, $loop);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Listening on port 80 failed (root and unused?)');
        }
        $connector = new Connector($loop);

        $server = new StreamingServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('http://127.0.0.1/', $response);

        $socket->close();
    }

    public function testSecureHttpsOnStandardPortReturnsUriWithNoPort()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        $loop = Factory::create();
        try {
            $socket = new Socket(443, $loop);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Listening on port 443 failed (root and unused?)');
        }
        $socket = new SecureServer($socket, $loop, array(
            'local_cert' => __DIR__ . '/../examples/localhost.pem'
        ));
        $connector = new Connector($loop, array(
            'tls' => array('verify_peer' => false)
        ));

        $server = new StreamingServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: 127.0.0.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('https://127.0.0.1/', $response);

        $socket->close();
    }

    public function testSecureHttpsOnStandardPortWithoutHostHeaderUsesSocketUri()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        $loop = Factory::create();
        try {
            $socket = new Socket(443, $loop);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Listening on port 443 failed (root and unused?)');
        }
        $socket = new SecureServer($socket, $loop, array(
            'local_cert' => __DIR__ . '/../examples/localhost.pem'
        ));
        $connector = new Connector($loop, array(
            'tls' => array('verify_peer' => false)
        ));

        $server = new StreamingServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('https://127.0.0.1/', $response);

        $socket->close();
    }

    public function testPlainHttpOnHttpsStandardPortReturnsUriWithPort()
    {
        $loop = Factory::create();
        try {
            $socket = new Socket(443, $loop);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Listening on port 443 failed (root and unused?)');
        }
        $connector = new Connector($loop);

        $server = new StreamingServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('http://127.0.0.1:443/', $response);

        $socket->close();
    }

    public function testSecureHttpsOnHttpStandardPortReturnsUriWithPort()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        $loop = Factory::create();
        try {
            $socket = new Socket(80, $loop);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Listening on port 80 failed (root and unused?)');
        }
        $socket = new SecureServer($socket, $loop, array(
            'local_cert' => __DIR__ . '/../examples/localhost.pem'
        ));
        $connector = new Connector($loop, array(
            'tls' => array('verify_peer' => false)
        ));

        $server = new StreamingServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri() . 'x' . $request->getHeaderLine('Host'));
        });

        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('https://127.0.0.1:80/', $response);

        $socket->close();
    }

    public function testClosedStreamFromRequestHandlerWillSendEmptyBody()
    {
        $loop = Factory::create();
        $connector = new Connector($loop);

        $stream = new ThroughStream();
        $stream->close();

        $server = new StreamingServer(function (RequestInterface $request) use ($stream) {
            return new Response(200, array(), $stream);
        });

        $socket = new Socket(0, $loop);
        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertStringStartsWith("HTTP/1.0 200 OK", $response);
        $this->assertStringEndsWith("\r\n\r\n", $response);

        $socket->close();
    }

    public function testRequestHandlerWillReceiveCloseEventIfConnectionClosesWhileSendingBody()
    {
        $loop = Factory::create();
        $connector = new Connector($loop);

        $once = $this->expectCallableOnce();
        $server = new StreamingServer(function (RequestInterface $request) use ($once) {
            $request->getBody()->on('close', $once);
        });

        $socket = new Socket(0, $loop);
        $server->listen($socket);

        $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) use ($loop) {
            $conn->write("GET / HTTP/1.0\r\nContent-Length: 100\r\n\r\n");

            $loop->addTimer(0.001, function() use ($conn) {
                $conn->end();
            });
        });

        Block\sleep(0.1, $loop);

        $socket->close();
    }

    public function testStreamFromRequestHandlerWillBeClosedIfConnectionClosesWhileSendingRequestBody()
    {
        $loop = Factory::create();
        $connector = new Connector($loop);

        $stream = new ThroughStream();

        $server = new StreamingServer(function (RequestInterface $request) use ($stream) {
            return new Response(200, array(), $stream);
        });

        $socket = new Socket(0, $loop);
        $server->listen($socket);

        $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) use ($loop) {
            $conn->write("GET / HTTP/1.0\r\nContent-Length: 100\r\n\r\n");

            $loop->addTimer(0.001, function() use ($conn) {
                $conn->end();
            });
        });

        // stream will be closed within 0.1s
        $ret = Block\await(Stream\first($stream, 'close'), $loop, 0.1);

        $socket->close();

        $this->assertNull($ret);
    }

    public function testStreamFromRequestHandlerWillBeClosedIfConnectionCloses()
    {
        $loop = Factory::create();
        $connector = new Connector($loop);

        $stream = new ThroughStream();

        $server = new StreamingServer(function (RequestInterface $request) use ($stream) {
            return new Response(200, array(), $stream);
        });

        $socket = new Socket(0, $loop);
        $server->listen($socket);

        $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) use ($loop) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            $loop->addTimer(0.1, function () use ($conn) {
                $conn->close();
            });
        });

        // await response stream to be closed
        $ret = Block\await(Stream\first($stream, 'close'), $loop, 1.0);

        $socket->close();

        $this->assertNull($ret);
    }

    public function testUpgradeWithThroughStreamReturnsDataAsGiven()
    {
        $loop = Factory::create();
        $connector = new Connector($loop);

        $server = new StreamingServer(function (RequestInterface $request) use ($loop) {
            $stream = new ThroughStream();

            $loop->addTimer(0.1, function () use ($stream) {
                $stream->end();
            });

            return new Response(101, array('Upgrade' => 'echo'), $stream);
        });

        $socket = new Socket(0, $loop);
        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.1\r\nHost: example.com:80\r\nUpgrade: echo\r\n\r\n");

            $conn->once('data', function () use ($conn) {
                $conn->write('hello');
                $conn->write('world');
            });

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertStringStartsWith("HTTP/1.1 101 Switching Protocols\r\n", $response);
        $this->assertStringEndsWith("\r\n\r\nhelloworld", $response);

        $socket->close();
    }

    public function testUpgradeWithRequestBodyAndThroughStreamReturnsDataAsGiven()
    {
        $loop = Factory::create();
        $connector = new Connector($loop);

        $server = new StreamingServer(function (RequestInterface $request) use ($loop) {
            $stream = new ThroughStream();

            $loop->addTimer(0.1, function () use ($stream) {
                $stream->end();
            });

            return new Response(101, array('Upgrade' => 'echo'), $stream);
        });

        $socket = new Socket(0, $loop);
        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("POST / HTTP/1.1\r\nHost: example.com:80\r\nUpgrade: echo\r\nContent-Length: 3\r\n\r\n");
            $conn->write('hoh');

            $conn->once('data', function () use ($conn) {
                $conn->write('hello');
                $conn->write('world');
            });

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertStringStartsWith("HTTP/1.1 101 Switching Protocols\r\n", $response);
        $this->assertStringEndsWith("\r\n\r\nhelloworld", $response);

        $socket->close();
    }

    public function testConnectWithThroughStreamReturnsDataAsGiven()
    {
        $loop = Factory::create();
        $connector = new Connector($loop);

        $server = new StreamingServer(function (RequestInterface $request) use ($loop) {
            $stream = new ThroughStream();

            $loop->addTimer(0.1, function () use ($stream) {
                $stream->end();
            });

            return new Response(200, array(), $stream);
        });

        $socket = new Socket(0, $loop);
        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\n\r\n");

            $conn->once('data', function () use ($conn) {
                $conn->write('hello');
                $conn->write('world');
            });

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertStringEndsWith("\r\n\r\nhelloworld", $response);

        $socket->close();
    }

    public function testConnectWithThroughStreamReturnedFromPromiseReturnsDataAsGiven()
    {
        $loop = Factory::create();
        $connector = new Connector($loop);

        $server = new StreamingServer(function (RequestInterface $request) use ($loop) {
            $stream = new ThroughStream();

            $loop->addTimer(0.1, function () use ($stream) {
                $stream->end();
            });

            return new Promise\Promise(function ($resolve) use ($loop, $stream) {
                $loop->addTimer(0.001, function () use ($resolve, $stream) {
                    $resolve(new Response(200, array(), $stream));
                });
            });
        });

        $socket = new Socket(0, $loop);
        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\n\r\n");

            $conn->once('data', function () use ($conn) {
                $conn->write('hello');
                $conn->write('world');
            });

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertStringEndsWith("\r\n\r\nhelloworld", $response);

        $socket->close();
    }

    public function testConnectWithClosedThroughStreamReturnsNoData()
    {
        $loop = Factory::create();
        $connector = new Connector($loop);

        $server = new StreamingServer(function (RequestInterface $request) {
            $stream = new ThroughStream();
            $stream->close();

            return new Response(200, array(), $stream);
        });

        $socket = new Socket(0, $loop);
        $server->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\n\r\n");

            $conn->once('data', function () use ($conn) {
                $conn->write('hello');
                $conn->write('world');
            });

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $response);
        $this->assertStringEndsWith("\r\n\r\n", $response);

        $socket->close();
    }

    public function testLimitConcurrentRequestsMiddlewareRequestStreamPausing()
    {
        $loop = Factory::create();
        $connector = new Connector($loop);

        $server = new StreamingServer(array(
            new LimitConcurrentRequestsMiddleware(5),
            new RequestBodyBufferMiddleware(16 * 1024 * 1024), // 16 MiB
            function (ServerRequestInterface $request, $next) use ($loop) {
                return new Promise\Promise(function ($resolve) use ($request, $loop, $next) {
                    $loop->addTimer(0.1, function () use ($request, $resolve, $next) {
                        $resolve($next($request));
                    });
                });
            },
            function (ServerRequestInterface $request) {
                return new Response(200, array(), (string)strlen((string)$request->getBody()));
            }
        ));

        $socket = new Socket(0, $loop);
        $server->listen($socket);

        $result = array();
        for ($i = 0; $i < 6; $i++) {
            $result[] = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
                $conn->write(
                    "GET / HTTP/1.0\r\nContent-Length: 1024\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n" .
                    str_repeat('a', 1024) .
                    "\r\n\r\n"
                );

                return Stream\buffer($conn);
            });
        }

        $responses = Block\await(Promise\all($result), $loop, 1.0);

        foreach ($responses as $response) {
            $this->assertContains("HTTP/1.0 200 OK", $response, $response);
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
