<?php

// Simple JSON-based HTTP API example as a base to build RESTful/RESTish APIs
// Launch demo and use your favorite CLI tool to test API requests
//
// $ php examples/59-server-json-api.php 8080
// $ curl -v http://localhost:8080/ -H 'Content-Type: application/json' -d '{"name":"Alice"}'

use React\Http\Message\Response;

require __DIR__ . '/../vendor/autoload.php';

$http = new React\Http\HttpServer(function (Psr\Http\Message\ServerRequestInterface $request) {
    if ($request->getHeaderLine('Content-Type') !== 'application/json') {
        return Response::json(
            array('error' => 'Only supports application/json')
        )->withStatus(Response::STATUS_UNSUPPORTED_MEDIA_TYPE);
    }

    $input = json_decode($request->getBody()->getContents());
    if (json_last_error() !== JSON_ERROR_NONE) {
        return Response::json(
            array('error' => 'Invalid JSON data given')
        )->withStatus(Response::STATUS_BAD_REQUEST);
    }

    if (!isset($input->name) || !is_string($input->name)) {
        return Response::json(
            array('error' => 'JSON data does not contain a string "name" property')
        )->withStatus(Response::STATUS_UNPROCESSABLE_ENTITY);
    }

    return Response::json(
        array('message' => 'Hello ' . $input->name)
    );
});

$socket = new React\Socket\SocketServer(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$http->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
