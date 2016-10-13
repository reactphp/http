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

## Usage

### Server

See the above usage example and the class outline for details.

### Request

See the above usage example and the class outline for details.

#### getHeaders()

The `getHeaders(): array` method can be used to
return ALL headers.

This will return an (possibly empty) assoc array with header names as
key and header values as value. The header value will be a string if
there's only a single value or an array of strings if this header has
multiple values.

Note that this differs from the PSR-7 implementation of this method.

#### getHeader()

The `getHeader(string $name): string[]` method can be used to
retrieve a message header value by the given case-insensitive name.

Returns a list of all values for this header name or an empty array if header was not found

#### getHeaderLine()

The `getHeaderLine(string $name): string` method can be used to
retrieve a comma-separated string of the values for a single header.

Returns a comma-separated list of all values for this header name or an empty string if header was not found

#### hasHeader()

The `hasHeader(string $name): bool` method can be used to
check if a header exists by the given case-insensitive name.

### Response

See the above usage example and the class outline for details.

## Install

The recommended way to install this library is [through Composer](http://getcomposer.org).
[New to Composer?](http://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require react/http:^0.4.3
```

More details about version upgrades can be found in the [CHANGELOG](CHANGELOG.md).

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](http://getcomposer.org):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
```

## License

MIT, see [LICENSE file](LICENSE).
