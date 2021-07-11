<?php

require __DIR__ . '/../vendor/autoload.php';

// Note how this example uses the advanced `StreamingRequestMiddleware` to allow streaming
// the incoming HTTP request. This very simple example merely counts the size
// of the streaming body, it does not otherwise buffer its contents in memory.
$server = new React\Http\Server(
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
                $resolve(new React\Http\Message\Response(
                    200,
                    array(
                        'Content-Type' => 'text/plain'
                    ),
                    "Received $bytes bytes\n"
                ));
            });

            // an error occures e.g. on invalid chunked encoded data or an unexpected 'end' event
            $body->on('error', function (\Exception $exception) use ($resolve, &$bytes) {
                $resolve(new React\Http\Message\Response(
                    400,
                    array(
                        'Content-Type' => 'text/plain'
                    ),
                    "Encountered error after $bytes bytes: {$exception->getMessage()}\n"
                ));
            });
        });
    }
);

$server->on('error', 'printf');

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
