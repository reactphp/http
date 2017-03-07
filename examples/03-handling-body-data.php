<?php

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Http\Request;
use React\Http\Response;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);

$server = new \React\Http\Server($socket);
$server->on('request', function (Request $request, Response $response) {
    $contentLength = 0;
    $request->on('data', function ($data) use (&$contentLength) {
        $contentLength += strlen($data);
    });

    $request->on('end', function () use ($response, &$contentLength){
        $response->writeHead(200, array('Content-Type' => 'text/plain'));
        $response->end("The length of the submitted request body is: " . $contentLength);
    });

    // an error occures e.g. on invalid chunked encoded data or an unexpected 'end' event
    $request->on('error', function (\Exception $exception) use ($response, &$contentLength) {
        $response->writeHead(400, array('Content-Type' => 'text/plain'));
        $response->end("An error occured while reading at length: " . $contentLength);
    });
});

echo 'Listening on http://' . $socket->getAddress() . PHP_EOL;

$loop->run();
