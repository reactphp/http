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

The `Request` class is responsible for streaming the incoming request body
and contains meta data which was parsed from the request headers.

It implements the `ReadableStreamInterface`.

See the above usage example and the class outline for details.

#### getHeaders()

The `getHeaders(): array` method can be used to
return an array with ALL headers.

The keys represent the header name in the exact case in which they were
originally specified. The values will be a string if there's only a single
value for the respective header name or an array of strings if this header
has multiple values.

> Note that this differs from the PSR-7 implementation of this method,
which always returns an array for each header name, even if it only has a
single value.

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

#### expectsContinue()

The `expectsContinue(): bool` method can be used to
check if the request headers contain the `Expect: 100-continue` header.

This header MAY be included when an HTTP/1.1 client wants to send a bigger
request body.
See [`writeContinue()`](#writecontinue) for more details.

This will always be `false` for HTTP/1.0 requests, regardless of what
any header values say.

### Response

The `Response` class is responsible for streaming the outgoing response body.

It implements the `WritableStreamInterface`.

See the above usage example and the class outline for details.

#### writeContinue()

The `writeContinue(): void` method can be used to
send an intermediary `HTTP/1.1 100 continue` response.

This is a feature that is implemented by *many* HTTP/1.1 clients.
When clients want to send a bigger request body, they MAY send only the request
headers with an additional `Expect: 100-continue` header and wait before
sending the actual (large) message body.

The server side MAY use this header to verify if the request message is
acceptable by checking the request headers (such as `Content-Length` or HTTP
authentication) and then ask the client to continue with sending the message body.
Otherwise, the server can send a normal HTTP response message and save the
client from transfering the whole body at all.

This method is mostly useful in combination with the
[`expectsContinue()`](#expectscontinue) method like this:

```php
$http->on('request', function (Request $request, Response $response) {
    if ($request->expectsContinue()) {
        $response->writeContinue();
    }

    $response->writeHead(200, array('Content-Type' => 'text/plain'));
    $response->end("Hello World!\n");
});
```

Note that calling this method is strictly optional.
If you do not use it, then the client MUST continue sending the request body
after waiting some time.

This method MUST NOT be invoked after calling `writeHead()`.
Calling this method after sending the headers will result in an `Exception`.

#### writeHead()

The `writeHead(int $status = 200, array $headers = array(): void` method can be used to
write the given HTTP message header.

This method MUST be invoked once before calling `write()` or `end()` to send
the actual HTTP message body:

```php
$response->writeHead(200, array(
    'Content-Type' => 'text/plain'
));
$response->end('Hello World!');
```

Calling this method more than once will result in an `Exception`.

Unless you specify a `Content-Length` header yourself, the response message
will automatically use chunked transfer encoding and send the respective header
(`Transfer-Encoding: chunked`) automatically. If you know the length of your
body, you MAY specify it like this instead:

```php
$data = 'Hello World!';

$response->writeHead(200, array(
    'Content-Type' => 'text/plain',
    'Content-Length' => strlen($data)
));
$response->end($data);
```

Note that it will automatically assume a `X-Powered-By: react/alpha` header
unless your specify a custom `X-Powered-By` header yourself:

```php
$response->writeHead(200, array(
    'X-Powered-By' => 'PHP 3'
));
```

If you do not want to send this header at all, you can use an empty array as
value like this:

```php
$response->writeHead(200, array(
    'X-Powered-By' => array()
));
```

## Install

The recommended way to install this library is [through Composer](http://getcomposer.org).
[New to Composer?](http://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require react/http:^0.4.4
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
