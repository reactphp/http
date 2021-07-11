<?php

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Http\Server;

require __DIR__ . '/../vendor/autoload.php';

$server = new Server(function (ServerRequestInterface $request) {
    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        "Hello world!\n"
    );
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$socket = new \React\Socket\SecureServer($socket, null, array(
    'local_cert' => isset($argv[2]) ? $argv[2] : __DIR__ . '/localhost.pem'
));
$server->listen($socket);

//$socket->on('error', 'printf');

echo 'Listening on ' . str_replace('tls:', 'https:', $socket->getAddress()) . PHP_EOL;
