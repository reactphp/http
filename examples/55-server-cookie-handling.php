<?php

require __DIR__ . '/../vendor/autoload.php';

$http = new React\Http\HttpServer(function (Psr\Http\Message\ServerRequestInterface $request) {
    $key = 'greeting';

    if (isset($request->getCookieParams()[$key])) {
        $body = "Your cookie value is: " . $request->getCookieParams()[$key] . "\n";

        return React\Http\Message\Response::plaintext(
            $body
        );
    }

    return React\Http\Message\Response::plaintext(
        "Your cookie has been set.\n"
    )->withHeader('Set-Cookie', $key . '=' . urlencode('Hello world!'));
});

$socket = new React\Socket\SocketServer(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$http->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
