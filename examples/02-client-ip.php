<?php

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Http\Response;
use Psr\Http\Message\ServerRequestInterface;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);

$server = new \React\Http\Server($socket, function (ServerRequestInterface $request) {
    $body = "Your IP is: " . $request->getServerParams()['remote_address'];

    return new Response(
        200,
        array('Content-Type' => 'text/plain'),
        $body
    );
});

echo 'Listening on http://' . $socket->getAddress() . PHP_EOL;

$loop->run();


