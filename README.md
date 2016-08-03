# Http Component

[![Build Status](https://secure.travis-ci.org/reactphp/http.png?branch=master)](http://travis-ci.org/reactphp/http) [![Code Climate](https://codeclimate.com/github/reactphp/http/badges/gpa.svg)](https://codeclimate.com/github/reactphp/http)

Library for building an evented http server.

This component builds on top of the `Socket` component to implement HTTP. Here
are the main concepts:

* **Server**: Attaches itself to an instance of
  `React\Socket\ServerInterface`, parses any incoming data as HTTP, emits a
  `request` event for each request.
* **Request**: A `ReadableStream` which streams the request body and contains
  meta data which was parsed from the request header.
* **Response** A `WritableStream` which streams the response body. You can set
  the status code and response headers via the `writeHead()` method.

## Usage

This is an HTTP server which responds with `Hello World` to every request.
```php
    $loop = React\EventLoop\Factory::create();
    $socket = new React\Socket\Server($loop);

    $http = new React\Http\Server($socket);
    $http->on('request', function ($request, $response) {
        $response->writeHead(200, array('Content-Type' => 'text/plain'));
        $response->end("Hello World!\n");
    });

    $socket->listen(1337);
    $loop->run();
```

## StreamingBodyParser\Factory and BufferedSink Usage

The `FormParserFactory` parses a request and determines which body parser to use (multipart, formurlencoded, raw body, or no body). Those body parsers emit events on `post` fields, `file` on files, and raw body emits `body` when it received the whole body. `DeferredStream` listens for those events and returns them through a promise when done.

```php
    $loop = React\EventLoop\Factory::create();
    $socket = new React\Socket\Server($loop);

    $http = new React\Http\Server($socket);
    $http->on('request', function ($request, $response) {
        $parser = React\Http\StreamingBodyParser\Factory::create($request);
        BufferedSink::createPromise($parser)->then(function ($result) use ($response) {
            var_export($result);
            $response->writeHead(200, array('Content-Type' => 'text/plain'));
            $response->end("Hello World!\n");
        });
    });

    $socket->listen(1337);
    $loop->run();
```
