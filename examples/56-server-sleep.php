<?php

use React\EventLoop\Loop;

require __DIR__ . '/../vendor/autoload.php';

$http = new React\Http\HttpServer(function (Psr\Http\Message\ServerRequestInterface $request) {
    $promise = new React\Promise\Promise(function ($resolve, $reject) {
        Loop::addTimer(1.5, function() use ($resolve) {
            $resolve();
        });
    });

    return $promise->then(function () {
        return React\Http\Message\Response::plaintext(
            "Hello world!\n"
        );
    });
});

$socket = new React\Socket\SocketServer(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$http->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
