# Http Component

[![Build Status](https://secure.travis-ci.org/reactphp/http.png?branch=master)](http://travis-ci.org/reactphp/http) [![Code Climate](https://codeclimate.com/github/reactphp/http/badges/gpa.svg)](https://codeclimate.com/github/reactphp/http)

Event-driven, streaming plaintext HTTP and secure HTTPS server for [ReactPHP](https://reactphp.org/)

**Table of Contents**

* [Quickstart example](#quickstart-example)
* [Usage](#usage)
  * [Server](#server)
  * [Request](#request)
  * [Response](#response)
* [Install](#install)
* [Tests](#tests)
* [License](#license)

## Quickstart example

This is an HTTP server which responds with `Hello World` to every request.

```php
$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server(8080, $loop);

$http = new Server($socket, function (ServerRequestInterface $request) {
    return new Response(
        200,
        array('Content-Type' => 'text/plain'),
        "Hello World!\n"
    );
});

$loop->run();
```

See also the [examples](examples).

## Usage

### Server

The `Server` class is responsible for handling incoming connections and then
processing each incoming HTTP request.

It attaches itself to an instance of `React\Socket\ServerInterface` which
emits underlying streaming connections in order to then parse incoming data
as HTTP.

For each request, it executes the callback function passed to the
constructor with the respective [request](#request) and
[response](#response) objects:

```php
$socket = new React\Socket\Server(8080, $loop);

$http = new Server($socket, function (ServerRequestInterface $request) {
    return new Response(
        200,
        array('Content-Type' => 'text/plain'),
        "Hello World!\n"
    );
});
```

See also the [first example](examples) for more details.

Similarly, you can also attach this to a
[`React\Socket\SecureServer`](https://github.com/reactphp/socket#secureserver)
in order to start a secure HTTPS server like this:

```php
$socket = new React\Socket\Server(8080, $loop);
$socket = new React\Socket\SecureServer($socket, $loop, array(
    'local_cert' => __DIR__ . '/localhost.pem'
));

$http = new Server($socket, function (ServerRequestInterface $request) {
    return new Response(
        200,
        array('Content-Type' => 'text/plain'),
        "Hello World!\n"
    );
});
```

See also [example #11](examples) for more details.

When HTTP/1.1 clients want to send a bigger request body, they MAY send only
the request headers with an additional `Expect: 100-continue` header and
wait before sending the actual (large) message body.
In this case the server will automatically send an intermediary
`HTTP/1.1 100 Continue` response to the client.
This ensures you will receive the request body without a delay as expected.
The [Response](#response) still needs to be created as described in the
examples above.

See also [request](#request) and [response](#response)
for more details (e.g. the request data body).

The `Server` supports both HTTP/1.1 and HTTP/1.0 request messages.
If a client sends an invalid request message, uses an invalid HTTP protocol
version or sends an invalid `Transfer-Encoding` in the request header, it will
emit an `error` event, send an HTTP error response to the client and
close the connection:

```php
$http->on('error', function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
```

The server will also emit an `error` event if you return an invalid
type in the callback function or have a unhandled `Exception`.
If your callback function throws an exception,
the `Server` will emit a `RuntimeException` and add the thrown exception
as previous:

```php
$http->on('error', function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    if ($e->getPrevious() !== null) {
        $previousException = $e->getPrevious();
        echo $previousException->getMessage() . PHP_EOL;
    }
});
```

Note that the request object can also emit an error.
Check out [request](#request) for more details.

### Request

An seen above, the `Server` class is responsible for handling incoming
connections and then processing each incoming HTTP request.

The request object will be processed once the request headers have
been received by the client.
This request object implements the
[PSR-7 ServerRequestInterface](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md#321-psrhttpmessageserverrequestinterface)
which in turn extends the
[PSR-7 RequestInterface](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md#32-psrhttpmessagerequestinterface)
and will be passed to the callback function like this.

```php 
$http = new Server($socket, function (ServerRequestInterface $request) {
    $body = "The method of the request is: " . $request->getMethod();
    $body .= "The requested path is: " . $request->getUri()->getPath();

    return new Response(
        200,
        array('Content-Type' => 'text/plain'),
        $body
    );
});
```

The `getServerParams(): mixed[]` method can be used to
get server-side parameters similar to the `$_SERVER` variable.
The following parameters are currently available:

* `remote_address`
  The IP address of the request sender
* `remote_port`
  Port of the request sender
* `server_address`
  The IP address of the server
* `server_port`
  The port of the server
* `request_time`
  Unix timestamp when the complete request header has been received,
  as integer similar to `time()`
* `request_time_float`
  Unix timestamp when the complete request header has been received,
  as float similar to `microtime(true)`
* `https`
  Set to 'on' if the request used HTTPS, otherwise it will be set to `null`

```php 
$http = new Server($socket, function (ServerRequestInterface $request) {
    $body = "Your IP is: " . $request->getServerParams()['remote_address'];

    return new Response(
        200,
        array('Content-Type' => 'text/plain'),
        $body
    );
});
```

See also [example #2](examples).

The `getCookieParams(): string[]` method can be used to
get all cookies sent with the current request.

```php 
$http = new Server($socket, function (ServerRequestInterface $request) {
    $key = 'react\php';

    if (isset($request->getCookieParams()[$key])) {
        $body = "Your cookie value is: " . $request->getCookieParams()[$key];

        return new Response(
            200,
            array('Content-Type' => 'text/plain'),
            $body
        );
    }

    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain',
            'Set-Cookie' => urlencode($key) . '=' . urlencode('test;more')
        ),
        "Your cookie has been set."
    );
});
```

The above example will try to set a cookie on first access and
will try to print the cookie value on all subsequent tries.
Note how the example uses the `urlencode()` function to encode
non-alphanumeric characters.
This encoding is also used internally when decoding the name and value of cookies
(which is in line with other implementations, such as PHP's cookie functions).

See also [example #7](examples) for more details.

For more details about the request object, check out the documentation of
[PSR-7 ServerRequestInterface](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md#321-psrhttpmessageserverrequestinterface)
and
[PSR-7 RequestInterface](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md#32-psrhttpmessagerequestinterface).

Note that the request object will be processed once the request headers have
been received.
This means that this happens irrespective of (i.e. *before*) receiving the
(potentially much larger) request body.
While this may be uncommon in the PHP ecosystem, this is actually a very powerful
approach that gives you several advantages not otherwise possible:

* React to requests *before* receiving a large request body,
  such as rejecting an unauthenticated request or one that exceeds allowed
  message lengths (file uploads).
* Start processing parts of the request body before the remainder of the request
  body arrives or if the sender is slowly streaming data.
* Process a large request body without having to buffer anything in memory,
  such as accepting a huge file upload or possibly unlimited request body stream.

The `getBody()` method can be used to access the request body stream.
This method returns a stream instance that implements both the
[PSR-7 StreamInterface](http://www.php-fig.org/psr/psr-7/#psrhttpmessagestreaminterface)
and the [ReactPHP ReadableStreamInterface](https://github.com/reactphp/stream#readablestreaminterface).
However, most of the `PSR-7 StreamInterface` methods have been
designed under the assumption of being in control of the request body.
Given that this does not apply to this server, the following
`PSR-7 StreamInterface` methods are not used and SHOULD NOT be called:
`tell()`, `eof()`, `seek()`, `rewind()`, `write()` and `read()`.
Instead, you should use the `ReactPHP ReadableStreamInterface` which
gives you access to the incoming request body as the individual chunks arrive:

```php
$http = new Server($socket, function (ServerRequestInterface $request) {
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
```

The above example simply counts the number of bytes received in the request body.
This can be used as a skeleton for buffering or processing the request body.

See also [example #4](examples) for more details.

The `data` event will be emitted whenever new data is available on the request
body stream.
The server automatically takes care of decoding chunked transfer encoding
and will only emit the actual payload as data.
In this case, the `Transfer-Encoding` header will be removed.

The `end` event will be emitted when the request body stream terminates
successfully, i.e. it was read until its expected end.

The `error` event will be emitted in case the request stream contains invalid
chunked data or the connection closes before the complete request stream has
been received.
The server will automatically `pause()` the connection instead of closing it.
A response message can still be sent (unless the connection is already closed).

A `close` event will be emitted after an `error` or `end` event.

For more details about the request body stream, check out the documentation of
[ReactPHP ReadableStreamInterface](https://github.com/reactphp/stream#readablestreaminterface).

The `getSize(): ?int` method can be used if you only want to know the request
body size.
This method returns the complete size of the request body as defined by the
message boundaries.
This value may be `0` if the request message does not contain a request body
(such as a simple `GET` request).
Note that this value may be `null` if the request body size is unknown in
advance because the request message uses chunked transfer encoding.

```php 
$http = new Server($socket, function (ServerRequestInterface $request) {
    $size = $request->getBody()->getSize();
    if ($size === null) {
        $body = 'The request does not contain an explicit length.';
        $body .= 'This server does not accept chunked transfer encoding.';

        return new Response(
            411,
            array('Content-Type' => 'text/plain'),
            $body
        );
    }

    return new Response(
        200,
        array('Content-Type' => 'text/plain'),
        "Request body size: " . $size . " bytes\n"
    );
});
```

Note that the server supports *any* request method (including custom and non-
standard ones) and all request-target formats defined in the HTTP specs for each
respective method.
You can use `getMethod(): string` and `getRequestTarget(): string` to
check this is an accepted request and may want to reject other requests with
an appropriate error code, such as `400` (Bad Request) or `405` (Method Not
Allowed).

> The `CONNECT` method is useful in a tunneling setup (HTTPS proxy) and not
  something most HTTP servers would want to care about.
  Note that if you want to handle this method, the client MAY send a different
  request-target than the `Host` header field (such as removing default ports)
  and the request-target MUST take precendence when forwarding.
  The HTTP specs define an opaque "tunneling mode" for this method and make no
  use of the message body.
  For consistency reasons, this library uses the message body of the request and
  response for tunneled application data.
  This implies that that a `2xx` (Successful) response to a `CONNECT` request
  can in fact use a streaming response body for the tunneled application data.
  See also [example #21](examples) for more details.

### Response

The callback function passed to the constructor of the [Server](#server)
is responsible for processing the request and returning a response,
which will be delivered to the client.
This function MUST return an instance imlementing
[PSR-7 ResponseInterface](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md#33-psrhttpmessageresponseinterface)
object or a 
[ReactPHP Promise](https://github.com/reactphp/promise#reactpromise)
which will resolve a `PSR-7 ResponseInterface` object.

You will find a `Response` class
which implements the `PSR-7 ResponseInterface` in this project.
We use instantiation of this class in our projects,
but feel free to use any implemantation of the 
`PSR-7 ResponseInterface` you prefer.

```php 
$http = new Server($socket, function (ServerRequestInterface $request) {
    return new Response(
        200,
        array('Content-Type' => 'text/plain'),
        "Hello World!\n"
    );
});
```

The example above returns the response directly, because it needs
no time to be processed.
Using a database, the file system or long calculations 
(in fact every action that will take >=1ms) to create your
response, will slow down the server.
To prevent this you SHOULD use a
[ReactPHP Promise](https://github.com/reactphp/promise#reactpromise).
This example shows how such a long-term action could look like:

```php
$server = new \React\Http\Server($socket, function (ServerRequestInterface $request) use ($loop) {
    return new Promise(function ($resolve, $reject) use ($request, $loop) {
        $loop->addTimer(1.5, function() use ($loop, $resolve) {
            $response = new Response(
                200,
                array('Content-Type' => 'text/plain'),
                "Hello world"
            );
            $resolve($response);
        });
    });
});
```

The above example will create a response after 1.5 second.
This example shows that you need a promise,
if your response needs time to created.
The `ReactPHP Promise` will resolve in a `Response` object when the request
body ends.

The `Response` class in this project supports to add an instance which implements the
[ReactPHP ReadableStreamInterface](https://github.com/reactphp/stream#readablestreaminterface)
for the response body.
So you are able stream data directly into the response body.
Note that other implementations of the `PSR-7 ResponseInterface` likely
only support string.

```php
$server = new Server($socket, function (ServerRequestInterface $request) use ($loop) {
    $stream = new ReadableStream();

    $timer = $loop->addPeriodicTimer(0.5, function () use ($stream) {
        $stream->emit('data', array(microtime(true) . PHP_EOL));
    });

    $loop->addTimer(5, function() use ($loop, $timer, $stream) {
        $loop->cancelTimer($timer);
        $stream->emit('end');
    });

    return new Response(200, array('Content-Type' => 'text/plain'), $stream);
});
```

The above example will emit every 0.5 seconds the current Unix timestamp 
with microseconds as float to the client and will end after 5 seconds.
This is just a example you could use of the streaming,
you could also send a big amount of data via little chunks 
or use it for body data that needs to calculated.

If the response body is a `string`, a `Content-Length` header will be added
automatically.
If the response body is a ReactPHP `ReadableStreamInterface` and you do not
specify a `Content-Length` header, HTTP/1.1 responses will automatically use
chunked transfer encoding and send the respective header
(`Transfer-Encoding: chunked`) automatically.
The server is responsible for handling `Transfer-Encoding`, so you SHOULD NOT
pass this header yourself.
If you know the length of your stream body, you MAY specify it like this instead:

```php
$stream = new ReadableStream()
$server = new Server($socket, function (ServerRequestInterface $request) use ($loop, $stream) {
    return new Response(
        200,
        array(
            'Content-Length' => '5',
            'Content-Type' => 'text/plain',
        ),
        $stream
    );
});
```

An invalid return value or an unhandled `Exception` in the code of the callback
function, will result in an `500 Internal Server Error` message.
Make sure to catch `Exceptions` to create own response messages.

After the return in the callback function the response will be processed by the `Server`.
The `Server` will add the protocol version of the request, so you don't have to.

Any response to a `HEAD` request and any response with a `1xx` (Informational),
`204` (No Content) or `304` (Not Modified) status code will *not* include a
message body as per the HTTP specs.
This means that your callback does not have to take special care of this and any
response body will simply be ignored.

Similarly, any `2xx` (Successful) response to a `CONNECT` request, any response
with a `1xx` (Informational) or `204` (No Content) status code will *not*
include a `Content-Length` or `Transfer-Encoding` header as these do not apply
to these messages.
Note that a response to a `HEAD` request and any response with a `304` (Not
Modified) status code MAY include these headers even though
the message does not contain a response body, because these header would apply
to the message if the same request would have used an (unconditional) `GET`.

> The `CONNECT` method is useful in a tunneling setup (HTTPS proxy) and not
  something most HTTP servers would want to care about.
  The HTTP specs define an opaque "tunneling mode" for this method and make no
  use of the message body.
  For consistency reasons, this library uses the message body of the request and
  response for tunneled application data.
  This implies that that a `2xx` (Successful) response to a `CONNECT` request
  can in fact use a streaming response body for the tunneled application data.
  See also [example #21](examples) for more details.

A `Date` header will be automatically added with the system date and time if none is given.
You can add a custom `Date` header yourself like this:

```php
$server = new Server($socket, function (ServerRequestInterface $request) {
    return new Response(200, array('Date' => date('D, d M Y H:i:s T')));
});
```

If you don't have a appropriate clock to rely on, you should
unset this header with an empty string:

```php
$server = new Server($socket, function (ServerRequestInterface $request) {
    return new Response(200, array('Date' => ''));
});
```

Note that it will automatically assume a `X-Powered-By: react/alpha` header
unless your specify a custom `X-Powered-By` header yourself:

```php
$server = new Server($socket, function (ServerRequestInterface $request) {
    return new Response(200, array('X-Powered-By' => 'PHP 3'));
});
```

If you do not want to send this header at all, you can use an empty string as
value like this:

```php
$server = new Server($socket, function (ServerRequestInterface $request) {
    return new Response(200, array('X-Powered-By' => ''));
});
```

Note that persistent connections (`Connection: keep-alive`) are currently
not supported.
As such, HTTP/1.1 response messages will automatically include a
`Connection: close` header, irrespective of what header values are
passed explicitly.

## Install

The recommended way to install this library is [through Composer](http://getcomposer.org).
[New to Composer?](http://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require react/http:^0.6
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
