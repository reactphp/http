<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Response;
use React\Promise\Promise;
use React\Socket\Server;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);

$server = new \React\Http\Server($socket, function (ServerRequestInterface $request) use ($loop) {
    return new Promise(function ($resolve, $reject) use ($request, $loop) {
        $loop->addTimer(1.5, function() use ($loop, $resolve) {
            $response = new Response(
                200,
                array('Content-Type' => 'text/plain'),
                "Hello world"
            );
            $resolve($response);
        });
    });
});

echo 'Listening on http://' . $socket->getAddress() . PHP_EOL;

$loop->run();
