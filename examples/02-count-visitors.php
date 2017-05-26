<?php

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Http\Response;
use Psr\Http\Message\ServerRequestInterface;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);

$counter = 0;
$server = new \React\Http\Server($socket, function (ServerRequestInterface $request) use (&$counter) {
    return new Response(
        200,
        array('Content-Type' => 'text/plain'),
        "Welcome number " . ++$counter . "!\n"
    );
});

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
