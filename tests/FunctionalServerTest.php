<?php

namespace React\Tests\Http;

use React\Socket\Server as Socket;
use React\EventLoop\Factory;
use React\Http\Server;
use Psr\Http\Message\RequestInterface;
use React\Socket\Connector;
use React\Socket\ConnectionInterface;
use React\Stream\BufferedSink;
use Clue\React\Block;
use React\Http\Response;
use React\Socket\SecureServer;

class FunctionServerTest extends TestCase
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
            $conn->write("GET / HTTP/1.0\r\nHost: " . $conn->getRemoteAddress() . "\r\n\r\n");

            return BufferedSink::createPromise($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('http://' . $socket->getAddress() . '/', $response);

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

        $result = $connector->connect('tls://' . $socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . $conn->getRemoteAddress() . "\r\n\r\n");

            return BufferedSink::createPromise($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('https://' . $socket->getAddress() . '/', $response);

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

            return BufferedSink::createPromise($conn);
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

        $result = $connector->connect('tls://' . $socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: 127.0.0.1\r\n\r\n");

            return BufferedSink::createPromise($conn);
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
            $conn->write("GET / HTTP/1.0\r\nHost: " . $conn->getRemoteAddress() . "\r\n\r\n");

            return BufferedSink::createPromise($conn);
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

        $result = $connector->connect('tls://' . $socket->getAddress())->then(function (ConnectionInterface $conn) {
            $conn->write("GET / HTTP/1.0\r\nHost: " . $conn->getRemoteAddress() . "\r\n\r\n");

            return BufferedSink::createPromise($conn);
        });

        $response = Block\await($result, $loop, 1.0);

        $this->assertContains("HTTP/1.0 200 OK", $response);
        $this->assertContains('https://127.0.0.1:80/', $response);

        $socket->close();
    }
}
