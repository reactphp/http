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


## Quickstart example

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

See also the [examples](examples).

## Request body parsing example

As of `v0.5` `react/http` supports request body parsing and comes with a handy factory:

```php
$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server($loop);

$http = new React\Http\Server($socket);
$http->on('request', function ($request, $response) {
    $parser = React\Http\StreamingBodyParser\Factory::create($request);
    $parser->on('body', function ($bodyString) {});
    $parser->on('post', function ($fieldName, $fileValue) {});
    $parser->on('file', function ($fieldName, $fileObject) {});
    $parser->on('end', function () use ($response) {
        $response->writeHead(200, array('Content-Type' => 'text/plain'));
        $response->end("Hello World!\n");
    });
});

$socket->listen(1337);
$loop->run();
```

### Current supported request body parsers

* RawBodyParser - Emit raw body chunks as they come in, no parsing for `post` or `file`. This is the default parser.
