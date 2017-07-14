<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Response;
use React\Http\Server;
use React\Promise\Promise;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$server = new Server(function (ServerRequestInterface $request) {
    return new Promise(function ($resolve, $reject) use ($request) {
        $contentLength = 0;
        $request->getBody()->on('data', function ($data) use (&$contentLength) {
            $contentLength += strlen($data);
        });

        $request->getBody()->on('end', function () use ($resolve, &$contentLength){
            $response = new Response(
                200,
                array('Content-Type' => 'text/plain'),
                "The length of the submitted request body is: " . $contentLength
            );
            $resolve($response);
        });

        // an error occures e.g. on invalid chunked encoded data or an unexpected 'end' event
        $request->getBody()->on('error', function (\Exception $exception) use ($resolve, &$contentLength) {
            $response = new Response(
                400,
                array('Content-Type' => 'text/plain'),
                "An error occured while reading at length: " . $contentLength
            );
            $resolve($response);
        });
    });
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
