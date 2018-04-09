<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Response;
use React\Http\Server;
use React\Stream\ThroughStream;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

// Note how this example still uses `Server` instead of `StreamingServer`.
// The `StreamingServer` is only required for streaming *incoming* requests.
$server = new Server(function (ServerRequestInterface $request) use ($loop) {
    if ($request->getMethod() !== 'GET' || $request->getUri()->getPath() !== '/') {
        return new Response(404);
    }

    $stream = new ThroughStream();

    // send some data every once in a while with periodic timer
    $timer = $loop->addPeriodicTimer(0.5, function () use ($stream) {
        $stream->write(microtime(true) . PHP_EOL);
    });

    // demo for ending stream after a few seconds
    $loop->addTimer(5.0, function() use ($stream) {
        $stream->end();
    });

    // stop timer if stream is closed (such as when connection is closed)
    $stream->on('close', function () use ($loop, $timer) {
        $loop->cancelTimer($timer);
    });

    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        $stream
    );
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
