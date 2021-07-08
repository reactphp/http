<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\Message\Response;
use React\Http\Server;
use React\Stream\ThroughStream;

require __DIR__ . '/../vendor/autoload.php';

$server = new Server(function (ServerRequestInterface $request) {
    if ($request->getMethod() !== 'GET' || $request->getUri()->getPath() !== '/') {
        return new Response(404);
    }

    $stream = new ThroughStream();

    // send some data every once in a while with periodic timer
    $timer = Loop::addPeriodicTimer(0.5, function () use ($stream) {
        $stream->write(microtime(true) . PHP_EOL);
    });

    // demo for ending stream after a few seconds
    Loop::addTimer(5.0, function() use ($stream) {
        $stream->end();
    });

    // stop timer if stream is closed (such as when connection is closed)
    $stream->on('close', function () use ($timer) {
        Loop::cancelTimer($timer);
    });

    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        $stream
    );
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
