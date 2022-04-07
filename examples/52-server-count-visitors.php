<?php

require __DIR__ . '/../vendor/autoload.php';

$counter = 0;
$http = new React\Http\HttpServer(function (Psr\Http\Message\ServerRequestInterface $request) use (&$counter) {
    return React\Http\Message\Response::plaintext(
        "Welcome number " . ++$counter . "!\n"
    );
});

$socket = new React\Socket\SocketServer(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$http->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
