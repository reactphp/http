<?php

require __DIR__ . '/../vendor/autoload.php';

// Note how this example uses the advanced `StreamingRequestMiddleware` to allow streaming
// the incoming HTTP request. This very simple example merely counts the size
// of the streaming body, it does not otherwise buffer its contents in memory.
$http = new React\Http\HttpServer(
    new React\Http\Middleware\StreamingRequestMiddleware(),
    function (Psr\Http\Message\ServerRequestInterface $request) {
        $body = $request->getBody();
        assert($body instanceof Psr\Http\Message\StreamInterface);
        assert($body instanceof React\Stream\ReadableStreamInterface);

        return new React\Promise\Promise(function ($resolve, $reject) use ($body) {
            $bytes = 0;
            $body->on('data', function ($data) use (&$bytes) {
                $bytes += strlen($data);
            });

            $body->on('end', function () use ($resolve, &$bytes){
                $resolve(React\Http\Message\Response::plaintext(
                    "Received $bytes bytes\n"
                ));
            });

            // an error occures e.g. on invalid chunked encoded data or an unexpected 'end' event
            $body->on('error', function (Exception $e) use ($resolve, &$bytes) {
                $resolve(React\Http\Message\Response::plaintext(
                    "Encountered error after $bytes bytes: {$e->getMessage()}\n"
                )->withStatus(React\Http\Message\Response::STATUS_BAD_REQUEST));
            });
        });
    }
);

$http->on('error', function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$socket = new React\Socket\SocketServer(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$http->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
