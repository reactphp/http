<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Message\Response;
use Fig\Http\Message\StatusCodeInterface;
use React\Http\Server;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$counter = 0;
$server = new Server($loop, function (ServerRequestInterface $request) use (&$counter) {
    return new Response(
        StatusCodeInterface::STATUS_OK,
        array(
            'Content-Type' => 'text/plain'
        ),
        "Welcome number " . ++$counter . "!\n"
    );
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
