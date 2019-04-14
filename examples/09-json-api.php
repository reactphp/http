<?php

// Simple JSON-based HTTP API example as a base to build RESTful/RESTish APIs
// Launch demo and use your favorite CLI tool to test API requests
//
// $ php examples/09-json-api.php 8080
// $ curl -v http://localhost:8080/ -H 'Content-Type: application/json' -d '{"name":"Alice"}'

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Response;
use React\Http\Server;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$server = new Server(function (ServerRequestInterface $request) {
    if ($request->getHeaderLine('Content-Type') !== 'application/json') {
        return new Response(
            415, // Unsupported Media Type
            array(
                'Content-Type' => 'application/json'
            ),
            json_encode(array('error' => 'Only supports application/json')) . "\n"
        );
    }

    $input = json_decode($request->getBody()->getContents());
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new Response(
            400, // Bad Request
            array(
                'Content-Type' => 'application/json'
            ),
            json_encode(array('error' => 'Invalid JSON data given')) . "\n"
        );
    }

    if (!isset($input->name) || !is_string($input->name)) {
        return new Response(
            422, // Unprocessable Entity
            array(
                'Content-Type' => 'application/json'
            ),
            json_encode(array('error' => 'JSON data does not contain a string "name" property')) . "\n"
        );
    }

    return new Response(
        200,
        array(
            'Content-Type' => 'application/json'
        ),
        json_encode(array('message' => 'Hello ' . $input->name)) . "\n"
    );
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
