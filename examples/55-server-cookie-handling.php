<?php

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

require __DIR__ . '/../vendor/autoload.php';

$http = new React\Http\HttpServer(function (ServerRequestInterface $request) {
    $key = 'react\php';

    if (isset($request->getCookieParams()[$key])) {
        $body = "Your cookie value is: " . $request->getCookieParams()[$key];

        return new Response(
            StatusCodeInterface::STATUS_OK,
            array(
                'Content-Type' => 'text/plain'
            ),
            $body
        );
    }

    return new Response(
        StatusCodeInterface::STATUS_OK,
        array(
            'Content-Type' => 'text/plain',
            'Set-Cookie' => urlencode($key) . '=' . urlencode('test;more')
        ),
        "Your cookie has been set."
    );
});

$socket = new React\Socket\SocketServer(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$http->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
