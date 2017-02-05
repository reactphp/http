<?php

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Http\Request;
use React\Http\Response;
use React\Socket\SecureServer;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$socket = new SecureServer($socket, $loop, array(
    'local_cert' => isset($argv[2]) ? $argv[2] : __DIR__ . '/localhost.pem'
));

$server = new \React\Http\Server($socket);
$server->on('request', function (Request $reques, Response $response) {
    $response->writeHead(200, array('Content-Type' => 'text/plain'));
    $response->end("Hello world!\n");
});

//$socket->on('error', 'printf');

echo 'Listening on https://' . $socket->getAddress() . PHP_EOL;

$loop->run();
