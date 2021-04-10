<?php

// $ php examples/72-server-http-connect-proxy.php 8080
// $ curl -v --proxy http://localhost:8080 https://reactphp.org/

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Message\Response;
use React\Http\Message\ResponseFactory;
use React\Http\Server;
use React\Socket\Connector;
use React\Socket\ConnectionInterface;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$connector = new Connector($loop);

// Note how this example uses the `Server` without the `StreamingRequestMiddleware`.
// Unlike the plain HTTP proxy, the CONNECT method does not contain a body
// and we establish an end-to-end connection over the stream object, so this
// doesn't have to store any payload data in memory at all.
$server = new Server($loop, function (ServerRequestInterface $request) use ($connector) {
    if ($request->getMethod() !== 'CONNECT') {
        return ResponseFactory::plain('This is a HTTP CONNECT (secure HTTPS) proxy')->withStatus(405);
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
            return ResponseFactory::plain('Unable to connect: ' . $e->getMessage())->withStatus(502);
        }
    );
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
