<?php

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Http\Response;
use React\Stream\ReadableStream;
use Psr\Http\Message\ServerRequestInterface;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);

$server = new \React\Http\Server($socket, function (ServerRequestInterface $request) use ($loop) {
    $stream = new ReadableStream();

    $timer = $loop->addPeriodicTimer(0.5, function () use ($stream) {
        $stream->emit('data', array(microtime(true) . PHP_EOL));
    });

    $loop->addTimer(5, function() use ($loop, $timer, $stream) {
        $loop->cancelTimer($timer);
        $stream->emit('end');
    });

    return new Response(
        200,
        array('Content-Type' => 'text/plain'),
        $stream
    );
});

echo 'Listening on http://' . $socket->getAddress() . PHP_EOL;

$loop->run();
