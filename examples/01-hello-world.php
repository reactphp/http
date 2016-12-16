<?php

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Http\Request;
use React\Http\Response;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server($loop);

$server = new \React\Http\Server($socket);
$server->on('request', function (Request $request, Response $response) {
    $response->writeHead(200, array('Content-Type' => 'text/plain'));
    $response->end("Hello world!\n");
});

$socket->listen(isset($argv[1]) ? $argv[1] : 0, '0.0.0.0');

echo 'Listening on ' . $socket->getPort() . PHP_EOL;

$loop->run();
