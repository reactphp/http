<?php

use React\EventLoop\Loop;
use React\Http\Message\Response;
use React\Stream\ThroughStream;

require __DIR__ . '/../vendor/autoload.php';

$http = new React\Http\HttpServer(function (Psr\Http\Message\ServerRequestInterface $request) {
    if ($request->getMethod() !== 'GET' || $request->getUri()->getPath() !== '/') {
        return new Response(Response::STATUS_NOT_FOUND);
    }

    $stream = new ThroughStream();

    // send some data every once in a while with periodic timer
    $timer = Loop::addPeriodicTimer(0.5, function () use ($stream) {
        $stream->write(microtime(true) . PHP_EOL);
    });

    // end stream after a few seconds
    $timeout = Loop::addTimer(5.0, function() use ($stream, $timer) {
        Loop::cancelTimer($timer);
        $stream->end();
    });

    // stop timer if stream is closed (such as when connection is closed)
    $stream->on('close', function () use ($timer, $timeout) {
        Loop::cancelTimer($timer);
        Loop::cancelTimer($timeout);
    });

    return new Response(
        Response::STATUS_OK,
        array(
            'Content-Type' => 'text/plain'
        ),
        $stream
    );
});

$socket = new React\Socket\SocketServer(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$http->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
