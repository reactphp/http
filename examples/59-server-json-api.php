<?php

// Simple JSON-based HTTP API example as a base to build RESTful/RESTish APIs
// Launch demo and use your favorite CLI tool to test API requests
//
// $ php examples/59-server-json-api.php 8080
// $ curl -v http://localhost:8080/ -H 'Content-Type: application/json' -d '{"name":"Alice"}'

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Message\ResponseFactory;
use React\Http\Server;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$server = new Server($loop, function (ServerRequestInterface $request) {
    if ($request->getHeaderLine('Content-Type') !== 'application/json') {
        return ResponseFactory::json(
            array('error' => 'Only supports application/json')
        )->withStatus(415); // Unsupported Media Type
    }

    $input = json_decode($request->getBody()->getContents());
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ResponseFactory::json(
            array('error' => 'Invalid JSON data given')
        )->withStatus(400); // Bad Request
    }

    if (!isset($input->name) || !is_string($input->name)) {
        return ResponseFactory::json(
            array('error' => 'JSON data does not contain a string "name" property')
        )->withStatus(422); // Unprocessable Entity
    }

    return ResponseFactory::json(array('message' => 'Hello ' . $input->name));
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
