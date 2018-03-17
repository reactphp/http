<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Response;
use React\Http\Server;
use React\Promise\Promise;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$server = new Server(function (ServerRequestInterface $request) use ($loop) {
    return new Promise(function ($resolve, $reject) use ($loop) {
        $loop->addTimer(1.5, function() use ($resolve) {
            $response = new Response(
                200,
                array(
                    'Content-Type' => 'text/plain'
                ),
                "Hello world"
            );
            $resolve($response);
        });
    });
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
