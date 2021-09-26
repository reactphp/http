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
        "Hello world!\n"
    );
});

$uri = 'tls://' . (isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$socket = new React\Socket\SocketServer($uri, array(
    'tls' => array(
        'local_cert' => isset($argv[2]) ? $argv[2] : __DIR__ . '/localhost.pem'
    )
));
$http->listen($socket);

$socket->on('error', function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

echo 'Listening on ' . str_replace('tls:', 'https:', $socket->getAddress()) . PHP_EOL;
