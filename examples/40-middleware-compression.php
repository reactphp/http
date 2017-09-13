<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Middleware\CompressionGzipMiddleware;
use React\Http\Response;
use React\Http\Server;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$server = new Server(new \React\Http\MiddlewareRunner(array(
  new CompressionGzipMiddleware(),
  function (ServerRequestInterface $request, $next) {
    return new Response(200, array(), str_repeat('A', 20000));
  },
)));

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
