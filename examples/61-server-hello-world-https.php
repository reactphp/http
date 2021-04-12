<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Message\ResponseFactory;
use React\Http\Server;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$server = new Server($loop, function (ServerRequestInterface $request) {
    return ResponseFactory::plain("Hello world\n");
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$socket = new \React\Socket\SecureServer($socket, $loop, array(
    'local_cert' => isset($argv[2]) ? $argv[2] : __DIR__ . '/localhost.pem'
));
$server->listen($socket);

//$socket->on('error', 'printf');

echo 'Listening on ' . str_replace('tls:', 'https:', $socket->getAddress()) . PHP_EOL;

$loop->run();
