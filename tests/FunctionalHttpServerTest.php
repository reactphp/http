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
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\SocketServer;
use React\Promise;
use React\Promise\Stream;
use React\Stream\ThroughStream;

class FunctionalHttpServerTest extends TestCase
{
    public function testPlainHttpOnRandomPort()
    {
        $connector = new Connector();

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

        $this->assertContainsString("HTTP/1.0 200 OK", $response);
        $this->assertContainsString('http://' . noScheme($socket->getAddress()) . '/', $response);

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

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

        $this->assertContainsString("HTTP/1.0 404 Not Found", $response);

        $socket->close();
    }

    public function testPlainHttpOnRandomPortWithoutHostHeaderUsesSocketUri()
    {
        $connector = new Connector();

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

        $this->assertContainsString("HTTP/1.0 200 OK", $response);
        $this->assertContainsString('http://' . noScheme($socket->getAddress()) . '/', $response);

        $socket->close();
    }

    public function testPlainHttpOnRandomPortWithOtherHostHeaderTakesPrecedence()
    {
        $connector = new Connector();

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: localhost:1000\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

        $this->assertContainsString("HTTP/1.0 200 OK", $response);
        $this->assertContainsString('http://localhost:1000/', $response);

        $socket->close();
    }

    public function testSecureHttpsOnRandomPort()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on HHVM');
        }

        $connector = new Connector(array(
            'tls' => array('verify_peer' => false)
        ));

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $socket = new SocketServer('tls://127.0.0.1:0', array('tls' => array(
            'local_cert' => __DIR__ . '/../examples/localhost.pem'
        )));
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

        $this->assertContainsString("HTTP/1.0 200 OK", $response);
        $this->assertContainsString('https://' . noScheme($socket->getAddress()) . '/', $response);

        $socket->close();
    }

    public function testSecureHttpsReturnsData()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on HHVM');
        }

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(
                200,
                array(),
                str_repeat('.', 33000)
            );
        });

        $socket = new SocketServer('tls://127.0.0.1:0', array('tls' => array(
            'local_cert' => __DIR__ . '/../examples/localhost.pem'
        )));
        $http->listen($socket);

        $connector = new Connector(array(
            'tls' => array('verify_peer' => false)
        ));

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

        $this->assertContainsString("HTTP/1.0 200 OK", $response);
        $this->assertContainsString("\r\nContent-Length: 33000\r\n", $response);
        $this->assertStringEndsWith("\r\n". str_repeat('.', 33000), $response);

        $socket->close();
    }

    public function testSecureHttpsOnRandomPortWithoutHostHeaderUsesSocketUri()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on HHVM');
        }

        $connector = new Connector(array(
            'tls' => array('verify_peer' => false)
        ));

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $socket = new SocketServer('tls://127.0.0.1:0', array('tls' => array(
            'local_cert' => __DIR__ . '/../examples/localhost.pem'
        )));
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

        $this->assertContainsString("HTTP/1.0 200 OK", $response);
        $this->assertContainsString('https://' . noScheme($socket->getAddress()) . '/', $response);

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
            return new Response(200, array(), (string)$request->getUri());
        });

        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: 127.0.0.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

        $this->assertContainsString("HTTP/1.0 200 OK", $response);
        $this->assertContainsString('http://127.0.0.1/', $response);

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
            return new Response(200, array(), (string)$request->getUri());
        });

        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

        $this->assertContainsString("HTTP/1.0 200 OK", $response);
        $this->assertContainsString('http://127.0.0.1/', $response);

        $socket->close();
    }

    public function testSecureHttpsOnStandardPortReturnsUriWithNoPort()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on HHVM');
        }

        try {
            $socket = new SocketServer('tls://127.0.0.1:443', array('tls' => array(
                'local_cert' => __DIR__ . '/../examples/localhost.pem'
            )));
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Listening on port 443 failed (root and unused?)');
        }

        $connector = new Connector(array(
            'tls' => array('verify_peer' => false)
        ));

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: 127.0.0.1\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

        $this->assertContainsString("HTTP/1.0 200 OK", $response);
        $this->assertContainsString('https://127.0.0.1/', $response);

        $socket->close();
    }

    public function testSecureHttpsOnStandardPortWithoutHostHeaderUsesSocketUri()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on HHVM');
        }

        try {
            $socket = new SocketServer('tls://127.0.0.1:443', array('tls' => array(
                'local_cert' => __DIR__ . '/../examples/localhost.pem'
            )));
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Listening on port 443 failed (root and unused?)');
        }

        $connector = new Connector(array(
            'tls' => array('verify_peer' => false)
        ));

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

        $this->assertContainsString("HTTP/1.0 200 OK", $response);
        $this->assertContainsString('https://127.0.0.1/', $response);

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
            return new Response(200, array(), (string)$request->getUri());
        });

        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

        $this->assertContainsString("HTTP/1.0 200 OK", $response);
        $this->assertContainsString('http://127.0.0.1:443/', $response);

        $socket->close();
    }

    public function testSecureHttpsOnHttpStandardPortReturnsUriWithPort()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on HHVM');
        }

        try {
            $socket = new SocketServer('tls://127.0.0.1:80', array('tls' => array(
                'local_cert' => __DIR__ . '/../examples/localhost.pem'
            )));
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Listening on port 80 failed (root and unused?)');
        }

        $connector = new Connector(array(
            'tls' => array('verify_peer' => false)
        ));

        $http = new HttpServer(function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri() . 'x' . $request->getHeaderLine('Host'));
        });

        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

        $this->assertContainsString("HTTP/1.0 200 OK", $response);
        $this->assertContainsString('https://127.0.0.1:80/', $response);

        $socket->close();
    }

    public function testClosedStreamFromRequestHandlerWillSendEmptyBody()
    {
        $connector = new Connector();

        $stream = new ThroughStream();
        $stream->close();

        $http = new HttpServer(function (RequestInterface $request) use ($stream) {
            return new Response(200, array(), $stream);
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

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

        \React\Async\await(\React\Promise\Timer\sleep(0.1));

        $socket->close();
    }

    public function testStreamFromRequestHandlerWillBeClosedIfConnectionClosesWhileSendingStreamingRequestBody()
    {
        $connector = new Connector();

        $stream = new ThroughStream();

        $http = new HttpServer(
            new StreamingRequestMiddleware(),
            function (RequestInterface $request) use ($stream) {
                return new Response(200, array(), $stream);
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
        $ret = \React\Async\await(\React\Promise\Timer\timeout(Stream\first($stream, 'close'), 0.1));

        $socket->close();

        $this->assertNull($ret);
    }

    public function testStreamFromRequestHandlerWillBeClosedIfConnectionCloses()
    {
        $connector = new Connector();

        $stream = new ThroughStream();

        $http = new HttpServer(function (RequestInterface $request) use ($stream) {
            return new Response(200, array(), $stream);
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
        $ret = \React\Async\await(\React\Promise\Timer\timeout(Stream\first($stream, 'close'), 1.0));

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

            return new Response(101, array('Upgrade' => 'echo'), $stream);
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.1\r\nHost: example.com:80\r\nUpgrade: echo\r\n\r\n");

            $conn->once('data', function () use ($conn) {
                $conn->write('hello');
                $conn->write('world');
            });

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

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

            return new Response(101, array('Upgrade' => 'echo'), $stream);
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

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

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

            return new Response(200, array(), $stream);
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\nConnection: close\r\n\r\n");

            $conn->once('data', function () use ($conn) {
                $conn->write('hello');
                $conn->write('world');
            });

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

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

            return new Promise\Promise(function ($resolve) use ($stream) {
                Loop::addTimer(0.001, function () use ($resolve, $stream) {
                    $resolve(new Response(200, array(), $stream));
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

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

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

            return new Response(200, array(), $stream);
        });

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("CONNECT example.com:80 HTTP/1.1\r\nHost: example.com:80\r\nConnection: close\r\n\r\n");

            $conn->once('data', function () use ($conn) {
                $conn->write('hello');
                $conn->write('world');
            });

            return Stream\buffer($conn);
        });

        $response = \React\Async\await(\React\Promise\Timer\timeout($result, 1.0));

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
                return new Promise\Promise(function ($resolve) use ($request, $next) {
                    Loop::addTimer(0.1, function () use ($request, $resolve, $next) {
                        $resolve($next($request));
                    });
                });
            },
            function (ServerRequestInterface $request) {
                return new Response(200, array(), (string)strlen((string)$request->getBody()));
            }
        );

        $socket = new SocketServer('127.0.0.1:0');
        $http->listen($socket);

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

        $responses = \React\Async\await(\React\Promise\Timer\timeout(Promise\all($result), 1.0));

        foreach ($responses as $response) {
            $this->assertContainsString("HTTP/1.0 200 OK", $response, $response);
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
