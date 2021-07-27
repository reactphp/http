<?php

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

require __DIR__ . '/../vendor/autoload.php';

$counter = 0;
$http = new React\Http\HttpServer(function (ServerRequestInterface $request) use (&$counter) {
    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        "Welcome number " . ++$counter . "!\n"
    );
});

$socket = new React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$http->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
