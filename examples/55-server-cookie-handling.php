<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Response;
use React\Http\Server;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$server = new Server(function (ServerRequestInterface $request) {
    $key = 'react\php';

    if (isset($request->getCookieParams()[$key])) {
        $body = "Your cookie value is: " . $request->getCookieParams()[$key];

        return new Response(
            200,
            array(
                'Content-Type' => 'text/plain'
            ),
            $body
        );
    }

    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain',
            'Set-Cookie' => urlencode($key) . '=' . urlencode('test;more')
        ),
        "Your cookie has been set."
    );
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
