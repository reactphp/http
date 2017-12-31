<?php

use React\Http\Server;
use React\Http\Response;
use React\EventLoop\Factory;
use Psr\Http\Message\ServerRequestInterface;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$server = new Server(array(
    function (ServerRequestInterface $request, callable $next) {
        return $next($request)
            ->then(function(\Psr\Http\Message\ResponseInterface $response) {
                return $response->withHeader('X-Custom', 'foo');
            });
    },
    function (ServerRequestInterface $request) {
        return new Response(200, array('Content-Type' => 'text/plain'),  "Hello world\n");
    }
));

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
