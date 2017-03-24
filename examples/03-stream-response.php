<?php

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Http\Response;
use Psr\Http\Message\RequestInterface;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);

$server = new \React\Http\Server($socket, function (RequestInterface $request, Response $response) use ($loop) {
    $response->writeHead(200, array('Content-Type' => 'text/plain'));

    $timer = $loop->addPeriodicTimer(0.5, function () use ($response) {
        $response->write(microtime(true) . PHP_EOL);
    });
    $loop->addTimer(5, function() use ($loop, $timer, $response) {
        $loop->cancelTimer($timer);
        $response->end();
    });
});

echo 'Listening on http://' . $socket->getAddress() . PHP_EOL;

$loop->run();
