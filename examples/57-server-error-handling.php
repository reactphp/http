<?php

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\Promise;

require __DIR__ . '/../vendor/autoload.php';

$count = 0;
$http = new React\Http\HttpServer(function (ServerRequestInterface $request) use (&$count) {
    return new Promise(function ($resolve, $reject) use (&$count) {
        $count++;

        if ($count%2 === 0) {
            throw new Exception('Second call');
        }

        $response = new Response(
            200,
            array(
                'Content-Type' => 'text/plain'
            ),
            "Hello World!\n"
        );

        $resolve($response);
    });
});

$socket = new React\Socket\SocketServer(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$http->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
