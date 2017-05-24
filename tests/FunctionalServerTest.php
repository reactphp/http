<?php

namespace React\Tests\Http;

use React\Socket\Server as Socket;
use React\EventLoop\Factory;
use React\Http\Server;
use Psr\Http\Message\RequestInterface;
use React\Socket\Connector;
use React\Socket\ConnectionInterface;
use Clue\React\Block;
use React\Http\Response;
use React\Socket\SecureServer;
use React\Stream\ReadableStreamInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Promise\Stream;
use React\Stream\ThroughStream;

class FunctionalServerTest extends TestCase
{
    public function testPlainHttpOnRandomPort()
    {
        $loop = Factory::create();
        $socket = new Socket(0, $loop);
        $connector = new Connector($loop);

        $server = new Server($socket, function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('http://' . noScheme($socket->getAddress()) . '/', $response);

        $socket->close();
    }

    public function testPlainHttpOnRandomPortWithoutHostHeaderUsesSocketUri()
    {
        $loop = Factory::create();
        $socket = new Socket(0, $loop);
        $connector = new Connector($loop);

        $server = new Server($socket, function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

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
        $socket = new Socket(0, $loop);
        $connector = new Connector($loop);

        $server = new Server($socket, function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

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
        $socket = new Socket(0, $loop);
        $socket = new SecureServer($socket, $loop, array(
            'local_cert' => __DIR__ . '/../examples/localhost.pem'
        ));
        $connector = new Connector($loop, array(
            'tls' => array('verify_peer' => false)
        ));

        $server = new Server($socket, function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $result = $connector->connect('tls://' . noScheme($socket->getAddress()))->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . noScheme($conn->getRemoteAddress()) . "\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('https://' . noScheme($socket->getAddress()) . '/', $response);

        $socket->close();
    }

    public function testSecureHttpsOnRandomPortWithoutHostHeaderUsesSocketUri()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        $loop = Factory::create();
        $socket = new Socket(0, $loop);
        $socket = new SecureServer($socket, $loop, array(
            'local_cert' => __DIR__ . '/../examples/localhost.pem'
        ));
        $connector = new Connector($loop, array(
            'tls' => array('verify_peer' => false)
        ));

        $server = new Server($socket, function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $result = $connector->connect('tls://' . noScheme($socket->getAddress()))->then(function (ConnectionInterface $conn) {
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

        $server = new Server($socket, function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

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

        $server = new Server($socket, function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

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

        $server = new Server($socket, function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $result = $connector->connect('tls://' . noScheme($socket->getAddress()))->then(function (ConnectionInterface $conn) {
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

        $server = new Server($socket, function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

        $result = $connector->connect('tls://' . noScheme($socket->getAddress()))->then(function (ConnectionInterface $conn) {
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

        $server = new Server($socket, function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri());
        });

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

        $server = new Server($socket, function (RequestInterface $request) {
            return new Response(200, array(), (string)$request->getUri() . 'x' . $request->getHeaderLine('Host'));
        });

        $result = $connector->connect('tls://' . noScheme($socket->getAddress()))->then(function (ConnectionInterface $conn) {
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
        $socket = new Socket(0, $loop);
        $connector = new Connector($loop);

        $stream = new ThroughStream();
        $stream->close();

        $server = new Server($socket, function (RequestInterface $request) use ($stream) {
            return new Response(200, array(), $stream);
        });

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) use ($loop) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertStringStartsWith("HTTP/1.0 200 OK", $response);
        $this->assertStringEndsWith("\r\n\r\n", $response);

        $socket->close();
    }

    public function testStreamFromRequestHandlerWillBeClosedIfConnectionClosesWhileSendingBody()
    {
        $loop = Factory::create();
        $socket = new Socket(0, $loop);
        $connector = new Connector($loop);

        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $server = new Server($socket, function (RequestInterface $request) use ($stream) {
            return new Response(200, array(), $stream);
        });

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) use ($loop) {
            $conn->write("GET / HTTP/1.0\r\nContent-Length: 100\r\n\r\n");

            $loop->addTimer(0.1, function() use ($conn) {
                $conn->end();
            });

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertStringStartsWith("HTTP/1.0 200 OK", $response);
        $this->assertStringEndsWith("\r\n\r\n", $response);

        $socket->close();
    }

    public function testStreamFromRequestHandlerWillBeClosedIfConnectionClosesButWillOnlyBeDetectedOnNextWrite()
    {
        $loop = Factory::create();
        $socket = new Socket(0, $loop);
        $connector = new Connector($loop);

        $stream = new ThroughStream();
        $stream->on('close', $this->expectCallableOnce());

        $server = new Server($socket, function (RequestInterface $request) use ($stream) {
            return new Response(200, array(), $stream);
        });

        $result = $connector->connect($socket->getAddress())->then(function (ConnectionInterface $conn) use ($loop) {
            $conn->write("GET / HTTP/1.0\r\n\r\n");

            $loop->addTimer(0.1, function() use ($conn) {
                $conn->end();
            });

            return Stream\buffer($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $stream->write('nope');
        Block\sleep(0.1, $loop);
        $stream->write('nope');
        Block\sleep(0.1, $loop);

        $this->assertStringStartsWith("HTTP/1.0 200 OK", $response);
        $this->assertStringEndsWith("\r\n\r\n", $response);

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
