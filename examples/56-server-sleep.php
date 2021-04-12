<?php

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\Message\Response;
use React\Promise\Promise;

require __DIR__ . '/../vendor/autoload.php';

$http = new React\Http\HttpServer(function (ServerRequestInterface $request) {
    return new Promise(function ($resolve, $reject) {
        Loop::addTimer(1.5, function() use ($resolve) {
            $response = new Response(
                StatusCodeInterface::STATUS_OK,
                array(
                    'Content-Type' => 'text/plain'
                ),
                "Hello world"
            );
            $resolve($response);
        });
    });
});

$socket = new React\Socket\SocketServer(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$http->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
