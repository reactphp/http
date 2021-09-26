<?php

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

require __DIR__ . '/../vendor/autoload.php';

$http = new React\Http\HttpServer(function (ServerRequestInterface $request) {
    return new Response(
        Response::STATUS_OK,
        array(
            'Content-Type' => 'text/plain'
        ),
        "Hello world\n"
    );
});

$socket = new React\Socket\SocketServer(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$http->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
