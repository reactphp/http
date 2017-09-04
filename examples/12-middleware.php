<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Middleware\Buffer;
use React\Http\Middleware\LimitHandlers;
use React\Http\MiddlewareRunner;
use React\Http\Response;
use React\Http\Server;
use React\Promise\Deferred;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$server = new Server(new MiddlewareRunner(
    new Response(404),
    array(
        new LimitHandlers(3), // Only handle three requests concurrently
        new Buffer(), // Buffer the whole request body before proceeding
        function (ServerRequestInterface $request) use ($loop) {
            $deferred = new Deferred();
            $loop->futureTick(function () use ($deferred) {
                $deferred->resolve(new Response(
                    200,
                    array(
                        'Content-Type' => 'text/plain'
                    ),
                    "Hello world\n"
                ));
            });
            return $deferred->promise();
        },
    )
));

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
