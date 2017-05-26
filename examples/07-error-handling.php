<?php

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Http\Response;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Promise;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);

$count = 0;
$server = new \React\Http\Server($socket, function (ServerRequestInterface $request) use (&$count) {
    return new Promise(function ($resolve, $reject) use (&$count) {
        $count++;

        if ($count%2 === 0) {
            throw new Exception('Second call');
        }

        $response = new Response(
            200,
            array('Content-Type' => 'text/plain'),
            "Hello World!\n"
        );

        $resolve($response);
    });
});

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
