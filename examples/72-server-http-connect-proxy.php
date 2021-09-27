<?php

// $ php examples/72-server-http-connect-proxy.php 8080
// $ curl -v --proxy http://localhost:8080 https://reactphp.org/

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Socket\Connector;
use React\Socket\ConnectionInterface;

require __DIR__ . '/../vendor/autoload.php';

$connector = new Connector();

// Note how this example uses the `HttpServer` without the `StreamingRequestMiddleware`.
// Unlike the plain HTTP proxy, the CONNECT method does not contain a body
// and we establish an end-to-end connection over the stream object, so this
// doesn't have to store any payload data in memory at all.
$http = new React\Http\HttpServer(function (ServerRequestInterface $request) use ($connector) {
    if ($request->getMethod() !== 'CONNECT') {
        return new Response(
            405,
            array(
                'Content-Type' => 'text/plain',
                'Allow' => 'CONNECT'
            ),
            'This is an HTTP CONNECT (secure HTTPS) proxy'
        );
    }

    // try to connect to given target host
    return $connector->connect($request->getRequestTarget())->then(
        function (ConnectionInterface $remote) {
            // connection established => forward data
            return new Response(
                200,
                array(),
                $remote
            );
        },
        function ($e) {
            return new Response(
                502,
                array(
                    'Content-Type' => 'text/plain'
                ),
                'Unable to connect: ' . $e->getMessage()
            );
        }
    );
});

$socket = new React\Socket\SocketServer(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$http->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
