<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Message\ResponseFactory;
use React\Http\Server;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$server = new Server($loop, function (ServerRequestInterface $request) {
    $key = 'react\php';

    if (isset($request->getCookieParams()[$key])) {
        $body = "Your cookie value is: " . $request->getCookieParams()[$key];

        return ResponseFactory::plain($body);
    }

    return ResponseFactory::plain('Your cookie has been set.')->withAddedHeader('Set-Cookie', urlencode($key) . '=' . urlencode('test;more'));
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
