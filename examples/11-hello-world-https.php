<?php

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Http\Response;
use React\Socket\SecureServer;
use Psr\Http\Message\ServerRequestInterface;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$socket = new SecureServer($socket, $loop, array(
    'local_cert' => isset($argv[2]) ? $argv[2] : __DIR__ . '/localhost.pem'
));

$server = new \React\Http\Server($socket, function (ServerRequestInterface $request) {
    return new Response(
        200,
        array('Content-Type' => 'text/plain'),
        "Hello world!\n"
    );
});

//$socket->on('error', 'printf');

echo 'Listening on https://' . $socket->getAddress() . PHP_EOL;

$loop->run();
