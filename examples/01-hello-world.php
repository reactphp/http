<?php

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Http\Response;
use Psr\Http\Message\RequestInterface;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);

$server = new \React\Http\Server($socket, function (RequestInterface $request) {
    return new Response(
        200,
        array(
            'Content-Length' => strlen("Hello world\n"),
            'Content-Type' => 'text/plain'
        ),
        "Hello world\n"
    );
});

echo 'Listening on http://' . $socket->getAddress() . PHP_EOL;

$loop->run();
