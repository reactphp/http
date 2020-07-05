# Http

[![Build Status](https://travis-ci.org/reactphp/http.svg?branch=master)](https://travis-ci.org/reactphp/http)

Event-driven, streaming plaintext HTTP and secure HTTPS server for [ReactPHP](https://reactphp.org/).

**Table of Contents**

* [Quickstart example](#quickstart-example)
* [Usage](#usage)
  * [Server](#server)
  * [StreamingServer](#streamingserver)
  * [listen()](#listen)
  * [Request](#request)
    * [Request parameters](#request-parameters)
    * [Query parameters](#query-parameters)
    * [Request body](#request-body)
    * [Streaming request](#streaming-request)
    * [Request method](#request-method)
    * [Cookie parameters](#cookie-parameters)
    * [Invalid request](#invalid-request)
  * [Response](#response)
    * [Deferred response](#deferred-response)
    * [Streaming response](#streaming-response)
    * [Response length](#response-length)
    * [Invalid response](#invalid-response)
    * [Default response headers](#default-response-headers)
  * [Middleware](#middleware)
    * [LimitConcurrentRequestsMiddleware](#limitconcurrentrequestsmiddleware)
    * [RequestBodyBufferMiddleware](#requestbodybuffermiddleware)
    * [RequestBodyParserMiddleware](#requestbodyparsermiddleware)
    * [Third-Party Middleware](#third-party-middleware)
* [Install](#install)
* [Tests](#tests)
* [License](#license)

## Quickstart example

This is an HTTP server which responds with `Hello World!` to every request.

```php
$loop = React\EventLoop\Factory::create();

$server = new Server(function (ServerRequestInterface $request) {
    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        "Hello World!\n"
    );
});

$socket = new React\Socket\Server(8080, $loop);
$server->listen($socket);

$loop->run();
```

See also the [examples](examples).

## Usage

### Server

The `Server` class is responsible for handling incoming connections and then
processing each incoming HTTP request.

It buffers and parses the complete incoming HTTP request in memory. Once the
complete request has been received, it will invoke the request handler function.
This request handler function needs to be passed to the constructor and will be
invoked with the respective [request](#request) object and expects a
[response](#response) object in return:

```php
$server = new Server(function (ServerRequestInterface $request) {
    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        "Hello World!\n"
    );
});
```

Each incoming HTTP request message is always represented by the
[PSR-7 `ServerRequestInterface`](https://www.php-fig.org/psr/psr-7/#321-psrhttpmessageserverrequestinterface),
see also following [request](#request) chapter for more details.
Each outgoing HTTP response message is always represented by the
[PSR-7 `ResponseInterface`](https://www.php-fig.org/psr/psr-7/#33-psrhttpmessageresponseinterface),
see also following [response](#response) chapter for more details.

In order to process any connections, the server needs to be attached to an
instance of `React\Socket\ServerInterface` through the [`listen()`](#listen) method
as described in the following chapter. In its most simple form, you can attach
this to a [`React\Socket\Server`](https://github.com/reactphp/socket#server)
in order to start a plaintext HTTP server like this:

```php
$server = new Server($handler);

$socket = new React\Socket\Server('0.0.0.0:8080', $loop);
$server->listen($socket);
```

See also the [`listen()`](#listen) method and the [first example](examples) for more details.

The `Server` class is built as a facade around the underlying
[`StreamingServer`](#streamingserver) to provide sane defaults for 80% of the
use cases and is the recommended way to use this library unless you're sure
you know what you're doing.

Unlike the underlying [`StreamingServer`](#streamingserver), this class
buffers and parses the complete incoming HTTP request in memory. Once the
complete request has been received, it will invoke the request handler
function. This means the [request](#request) passed to your request handler
function will be fully compatible with PSR-7.

On the other hand, buffering complete HTTP requests in memory until they can
be processed by your request handler function means that this class has to
employ a number of limits to avoid consuming too much memory. In order to
take the more advanced configuration out your hand, it respects setting from
your [`php.ini`](https://www.php.net/manual/en/ini.core.php) to apply its
default settings. This is a list of PHP settings this class respects with
their respective default values:

```
memory_limit 128M
post_max_size 8M
enable_post_data_reading 1
max_input_nesting_level 64
max_input_vars 1000

file_uploads 1
upload_max_filesize 2M
max_file_uploads 20
```

In particular, the `post_max_size` setting limits how much memory a single HTTP
request is allowed to consume while buffering its request body. On top of
this, this class will try to avoid consuming more than 1/4 of your
`memory_limit` for buffering multiple concurrent HTTP requests. As such, with
the above default settings of `128M` max, it will try to consume no more than
`32M` for buffering multiple concurrent HTTP requests. As a consequence, it
will limit the concurrency to 4 HTTP requests with the above defaults.

It is imperative that you assign reasonable values to your PHP ini settings.
It is usually recommended to either reduce the memory a single request is
allowed to take (set `post_max_size 1M` or less) or to increase the total memory
limit to allow for more concurrent requests (set `memory_limit 512M` or more).
Failure to do so means that this class may have to disable concurrency and
only handle one request at a time.

Internally, this class automatically assigns these limits to the
[middleware](#middleware) request handlers as described below. For more
advanced use cases, you may also use the advanced
[`StreamingServer`](#streamingserver) and assign these middleware request
handlers yourself as described in the following chapters.

### StreamingServer

The advanced `StreamingServer` class is responsible for handling incoming connections and then
processing each incoming HTTP request.

Unlike the [`Server`](#server) class, it does not buffer and parse the incoming
HTTP request body by default. This means that the request handler will be
invoked with a streaming request body. Once the request headers have been
received, it will invoke the request handler function. This request handler
function needs to be passed to the constructor and will be invoked with the
respective [request](#request) object and expects a [response](#response)
object in return:

```php
$server = new StreamingServer(function (ServerRequestInterface $request) {
    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        "Hello World!\n"
    );
});
```

Each incoming HTTP request message is always represented by the
[PSR-7 `ServerRequestInterface`](https://www.php-fig.org/psr/psr-7/#321-psrhttpmessageserverrequestinterface),
see also following [request](#request) chapter for more details.
Each outgoing HTTP response message is always represented by the
[PSR-7 `ResponseInterface`](https://www.php-fig.org/psr/psr-7/#33-psrhttpmessageresponseinterface),
see also following [response](#response) chapter for more details.

In order to process any connections, the server needs to be attached to an
instance of `React\Socket\ServerInterface` through the [`listen()`](#listen) method
as described in the following chapter. In its most simple form, you can attach
this to a [`React\Socket\Server`](https://github.com/reactphp/socket#server)
in order to start a plaintext HTTP server like this:

```php
$server = new StreamingServer($handler);

$socket = new React\Socket\Server('0.0.0.0:8080', $loop);
$server->listen($socket);
```

See also the [`listen()`](#listen) method and the [first example](examples) for more details.

The `StreamingServer` class is considered advanced usage and unless you know
what you're doing, you're recommended to use the [`Server`](#server) class
instead. The `StreamingServer` class is specifically designed to help with
more advanced use cases where you want to have full control over consuming
the incoming HTTP request body and concurrency settings.

In particular, this class does not buffer and parse the incoming HTTP request
in memory. It will invoke the request handler function once the HTTP request
headers have been received, i.e. before receiving the potentially much larger
HTTP request body. This means the [request](#request) passed to your request
handler function may not be fully compatible with PSR-7. See also
[streaming request](#streaming-request) below for more details.

### listen()

The `listen(React\Socket\ServerInterface $socket): void` method can be used to
start processing connections from the given socket server.
The given [`React\Socket\ServerInterface`](https://github.com/reactphp/socket#serverinterface)
is responsible for emitting the underlying streaming connections.
This HTTP server needs to be attached to it in order to process any connections
and pase incoming streaming data as incoming HTTP request messages.
In its most common form, you can attach this to a
[`React\Socket\Server`](https://github.com/reactphp/socket#server)
in order to start a plaintext HTTP server like this:

```php
$server = new Server($handler);
// or
$server = new StreamingServer($handler);

$socket = new React\Socket\Server('0.0.0.0:8080', $loop);
$server->listen($socket);
```

This example will start listening for HTTP requests on the alternative HTTP port
`8080` on all interfaces (publicly). As an alternative, it is very common to use
a reverse proxy and let this HTTP server listen on the localhost (loopback)
interface only by using the listen address `127.0.0.1:8080` instead. This way, you
host your application(s) on the default HTTP port `80` and only route specific
requests to this HTTP server.

Likewise, it's usually recommended to use a reverse proxy setup to accept
secure HTTPS requests on default HTTPS port `443` (TLS termination) and only
route plaintext requests to this HTTP server. As an alternative, you can also
accept secure HTTPS requests with this HTTP server by attaching this to a
[`React\Socket\Server`](https://github.com/reactphp/socket#server) using a
secure TLS listen address, a certificate file and optional `passphrase` like this:

```php
$server = new Server($handler);
// or
$server = new StreamingServer($handler);

$socket = new React\Socket\Server('tls://0.0.0.0:8443', $loop, array(
    'local_cert' => __DIR__ . '/localhost.pem'
));
$server->listen($socket);
```

See also [example #11](examples) for more details.

### Request

As seen above, the [`Server`](#server) and [`StreamingServer`](#streamingserver)
classes are responsible for handling incoming connections and then processing
each incoming HTTP request.

The request object will be processed once the request has
been received by the client.
This request object implements the
[PSR-7 ServerRequestInterface](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md#321-psrhttpmessageserverrequestinterface)
which in turn extends the
[PSR-7 RequestInterface](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md#32-psrhttpmessagerequestinterface)
and will be passed to the callback function like this.

 ```php 
$server = new Server(function (ServerRequestInterface $request) {
    $body = "The method of the request is: " . $request->getMethod();
    $body .= "The requested path is: " . $request->getUri()->getPath();

    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        $body
    );
});
```

For more details about the request object, also check out the documentation of
[PSR-7 ServerRequestInterface](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md#321-psrhttpmessageserverrequestinterface)
and
[PSR-7 RequestInterface](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md#32-psrhttpmessagerequestinterface).

#### Request parameters

The `getServerParams(): mixed[]` method can be used to
get server-side parameters similar to the `$_SERVER` variable.
The following parameters are currently available:

* `REMOTE_ADDR`
  The IP address of the request sender
* `REMOTE_PORT`
  Port of the request sender
* `SERVER_ADDR`
  The IP address of the server
* `SERVER_PORT`
  The port of the server
* `REQUEST_TIME`
  Unix timestamp when the complete request header has been received,
  as integer similar to `time()`
* `REQUEST_TIME_FLOAT`
  Unix timestamp when the complete request header has been received,
  as float similar to `microtime(true)`
* `HTTPS`
  Set to 'on' if the request used HTTPS, otherwise it won't be set

```php 
$server = new Server(function (ServerRequestInterface $request) {
    $body = "Your IP is: " . $request->getServerParams()['REMOTE_ADDR'];

    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        $body
    );
});
```

See also [example #3](examples).

> Advanced: Note that address parameters will not be set if you're listening on
  a Unix domain socket (UDS) path as this protocol lacks the concept of
  host/port.

#### Query parameters

The `getQueryParams(): array` method can be used to get the query parameters
similiar to the `$_GET` variable.

```php
$server = new Server(function (ServerRequestInterface $request) {
    $queryParams = $request->getQueryParams();

    $body = 'The query parameter "foo" is not set. Click the following link ';
    $body .= '<a href="/?foo=bar">to use query parameter in your request</a>';

    if (isset($queryParams['foo'])) {
        $body = 'The value of "foo" is: ' . htmlspecialchars($queryParams['foo']);
    }

    return new Response(
        200,
        array(
            'Content-Type' => 'text/html'
        ),
        $body
    );
});
```

The response in the above example will return a response body with a link.
The URL contains the query parameter `foo` with the value `bar`.
Use [`htmlentities`](https://www.php.net/manual/en/function.htmlentities.php)
like in this example to prevent
[Cross-Site Scripting (abbreviated as XSS)](https://en.wikipedia.org/wiki/Cross-site_scripting).

See also [example #4](examples).

#### Request body

If you're using the [`Server`](#server), then the request object will be
buffered and parsed in memory and contains the full request body.
This includes the parsed request body and any file uploads.

> If you're using the advanced [`StreamingServer`](#streamingserver) class, jump
  to the next chapter to learn more about how to process a
  [streaming request](#streaming-request).

As stated above, each incoming HTTP request is always represented by the
[PSR-7 `ServerRequestInterface`](https://www.php-fig.org/psr/psr-7/#321-psrhttpmessageserverrequestinterface).
This interface provides several methods that are useful when working with the
incoming request body as described below.

The `getParsedBody(): null|array|object` method can be used to
get the parsed request body, similar to
[PHP's `$_POST` variable](https://www.php.net/manual/en/reserved.variables.post.php).
This method may return a (possibly nested) array structure with all body
parameters or a `null` value if the request body could not be parsed.
By default, this method will only return parsed data for requests using
`Content-Type: application/x-www-form-urlencoded` or `Content-Type: multipart/form-data`
request headers (commonly used for `POST` requests for HTML form submission data).

```php
$server = new Server(function (ServerRequestInterface $request) {
    $name = $request->getParsedBody()['name'] ?? 'anonymous';

    return new Response(
        200,
        array(),
        "Hello $name!\n"
    );
});
```

See also [example #12](examples) for more details.

The `getBody(): StreamInterface` method can be used to
get the raw data from this request body, similar to
[PHP's `php://input` stream](https://www.php.net/manual/en/wrappers.php.php#wrappers.php.input).
This method returns an instance of the request body represented by the
[PSR-7 `StreamInterface`](https://www.php-fig.org/psr/psr-7/#34-psrhttpmessagestreaminterface).
This is particularly useful when using a custom request body that will not
otherwise be parsed by default, such as a JSON (`Content-Type: application/json`) or
an XML (`Content-Type: application/xml`) request body (which is commonly used for
`POST`, `PUT` or `PATCH` requests in JSON-based or RESTful/RESTish APIs).

```php
$server = new Server(function (ServerRequestInterface $request) {
    $data = json_decode((string)$request->getBody());
    $name = $data->name ?? 'anonymous';

    return new Response(
        200,
        array('Content-Type' => 'application/json'),
        json_encode(['message' => "Hello $name!"])
    );
});
```

See also [example #9](examples) for more details.

The `getUploadedFiles(): array` method can be used to
get the uploaded files in this request, similar to
[PHP's `$_FILES` variable](https://www.php.net/manual/en/reserved.variables.files.php).
This method returns a (possibly nested) array structure with all file uploads, each represented by the
[PSR-7 `UploadedFileInterface`](https://www.php-fig.org/psr/psr-7/#36-psrhttpmessageuploadedfileinterface).
This array will only be filled when using the `Content-Type: multipart/form-data`
request header (commonly used for `POST` requests for HTML file uploads).

```php
$server = new Server(function (ServerRequestInterface $request) {
    $files = $request->getUploadedFiles();
    $name = isset($files['avatar']) ? $files['avatar']->getClientFilename() : 'nothing';

    return new Response(
        200,
        array(),
        "Uploaded $name\n"
    );
});
```

See also [example #12](examples) for more details.

The `getSize(): ?int` method can be used to
get the size of the request body, similar to PHP's `$_SERVER['CONTENT_LENGTH']` variable.
This method returns the complete size of the request body measured in number
of bytes as defined by the message boundaries.
This value may be `0` if the request message does not contain a request body
(such as a simple `GET` request).
This method operates on the buffered request body, i.e. the request body size
is always known, even when the request does not specify a `Content-Length` request
header or when using `Transfer-Encoding: chunked` for HTTP/1.1 requests.

> Note: The `Server` automatically takes care of handling requests with the
  additional `Expect: 100-continue` request header. When HTTP/1.1 clients want to
  send a bigger request body, they MAY send only the request headers with an
  additional `Expect: 100-continue` request header and wait before sending the actual
  (large) message body. In this case the server will automatically send an
  intermediary `HTTP/1.1 100 Continue` response to the client. This ensures you
  will receive the request body without a delay as expected.

#### Streaming request

If you're using the advanced [`StreamingServer`](#streamingserver), the
request object will be processed once the request headers have been received.
This means that this happens irrespective of (i.e. *before*) receiving the
(potentially much larger) request body.

> If you're using the [`Server`](#server) class, jump to the previous chapter
  to learn more about how to process a buffered [request body](#request-body).

While this may be uncommon in the PHP ecosystem, this is actually a very powerful
approach that gives you several advantages not otherwise possible:

* React to requests *before* receiving a large request body,
  such as rejecting an unauthenticated request or one that exceeds allowed
  message lengths (file uploads).
* Start processing parts of the request body before the remainder of the request
  body arrives or if the sender is slowly streaming data.
* Process a large request body without having to buffer anything in memory,
  such as accepting a huge file upload or possibly unlimited request body stream.

The `getBody(): StreamInterface` method can be used to
access the request body stream.
In the streaming mode, this method returns a stream instance that implements both the
[PSR-7 `StreamInterface`](https://www.php-fig.org/psr/psr-7/#34-psrhttpmessagestreaminterface)
and the [ReactPHP `ReadableStreamInterface`](https://github.com/reactphp/stream#readablestreaminterface).
However, most of the PSR-7 `StreamInterface` methods have been
designed under the assumption of being in control of a synchronous request body.
Given that this does not apply to this server, the following
PSR-7 `StreamInterface` methods are not used and SHOULD NOT be called:
`tell()`, `eof()`, `seek()`, `rewind()`, `write()` and `read()`.
If this is an issue for your use case and/or you want to access uploaded files,
it's highly recommended to use a buffered [request body](#request-body) or use the
[`RequestBodyBufferMiddleware`](#requestbodybuffermiddleware) instead.
The ReactPHP `ReadableStreamInterface` gives you access to the incoming
request body as the individual chunks arrive:

```php
$server = new StreamingServer(function (ServerRequestInterface $request) {
    return new Promise(function ($resolve, $reject) use ($request) {
        $contentLength = 0;
        $request->getBody()->on('data', function ($data) use (&$contentLength) {
            $contentLength += strlen($data);
        });

        $request->getBody()->on('end', function () use ($resolve, &$contentLength){
            $response = new Response(
                200,
                array(
                    'Content-Type' => 'text/plain'
                ),
                "The length of the submitted request body is: " . $contentLength
            );
            $resolve($response);
        });

        // an error occures e.g. on invalid chunked encoded data or an unexpected 'end' event
        $request->getBody()->on('error', function (\Exception $exception) use ($resolve, &$contentLength) {
            $response = new Response(
                400,
                array(
                    'Content-Type' => 'text/plain'
                ),
                "An error occured while reading at length: " . $contentLength
            );
            $resolve($response);
        });
    });
});
```

The above example simply counts the number of bytes received in the request body.
This can be used as a skeleton for buffering or processing the request body.

See also [example #13](examples) for more details.

The `data` event will be emitted whenever new data is available on the request
body stream.
The server also automatically takes care of decoding any incoming requests using
`Transfer-Encoding: chunked` and will only emit the actual payload as data.

The `end` event will be emitted when the request body stream terminates
successfully, i.e. it was read until its expected end.

The `error` event will be emitted in case the request stream contains invalid
data for `Transfer-Encoding: chunked` or when the connection closes before
the complete request stream has been received.
The server will automatically stop reading from the connection and discard all
incoming data instead of closing it.
A response message can still be sent (unless the connection is already closed).

A `close` event will be emitted after an `error` or `end` event.

For more details about the request body stream, check out the documentation of
[ReactPHP ReadableStreamInterface](https://github.com/reactphp/stream#readablestreaminterface).

The `getSize(): ?int` method can be used to
get the size of the request body, similar to PHP's `$_SERVER['CONTENT_LENGTH']` variable.
This method returns the complete size of the request body measured in number
of bytes as defined by the message boundaries.
This value may be `0` if the request message does not contain a request body
(such as a simple `GET` request).
This method operates on the streaming request body, i.e. the request body size
may be unknown (`null`) when using `Transfer-Encoding: chunked` for HTTP/1.1 requests.

```php 
$server = new StreamingServer(function (ServerRequestInterface $request) {
    $size = $request->getBody()->getSize();
    if ($size === null) {
        $body = 'The request does not contain an explicit length.';
        $body .= 'This example does not accept chunked transfer encoding.';

        return new Response(
            411,
            array(
                'Content-Type' => 'text/plain'
            ),
            $body
        );
    }

    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        "Request body size: " . $size . " bytes\n"
    );
});
```

> Note: The `StreamingServer` automatically takes care of handling requests with the
  additional `Expect: 100-continue` request header. When HTTP/1.1 clients want to
  send a bigger request body, they MAY send only the request headers with an
  additional `Expect: 100-continue` request header and wait before sending the actual
  (large) message body. In this case the server will automatically send an
  intermediary `HTTP/1.1 100 Continue` response to the client. This ensures you
  will receive the streaming request body without a delay as expected.

#### Request method

Note that the server supports *any* request method (including custom and non-
standard ones) and all request-target formats defined in the HTTP specs for each
respective method, including *normal* `origin-form` requests as well as
proxy requests in `absolute-form` and `authority-form`.
The `getUri(): UriInterface` method can be used to get the effective request
URI which provides you access to individiual URI components.
Note that (depending on the given `request-target`) certain URI components may
or may not be present, for example the `getPath(): string` method will return
an empty string for requests in `asterisk-form` or `authority-form`.
Its `getHost(): string` method will return the host as determined by the
effective request URI, which defaults to the local socket address if a HTTP/1.0
client did not specify one (i.e. no `Host` header).
Its `getScheme(): string` method will return `http` or `https` depending
on whether the request was made over a secure TLS connection to the target host.

The `Host` header value will be sanitized to match this host component plus the
port component only if it is non-standard for this URI scheme.

You can use `getMethod(): string` and `getRequestTarget(): string` to
check this is an accepted request and may want to reject other requests with
an appropriate error code, such as `400` (Bad Request) or `405` (Method Not
Allowed).

> The `CONNECT` method is useful in a tunneling setup (HTTPS proxy) and not
  something most HTTP servers would want to care about.
  Note that if you want to handle this method, the client MAY send a different
  request-target than the `Host` header value (such as removing default ports)
  and the request-target MUST take precendence when forwarding.

#### Cookie parameters

The `getCookieParams(): string[]` method can be used to
get all cookies sent with the current request.

```php 
$server = new Server(function (ServerRequestInterface $request) {
    $key = 'react\php';

    if (isset($request->getCookieParams()[$key])) {
        $body = "Your cookie value is: " . $request->getCookieParams()[$key];

        return new Response(
            200,
            array(
                'Content-Type' => 'text/plain'
            ),
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

See also [example #5](examples) for more details.

#### Invalid request

The `Server` and `StreamingServer` classes support both HTTP/1.1 and HTTP/1.0 request
messages. If a client sends an invalid request message, uses an invalid HTTP
protocol version or sends an invalid `Transfer-Encoding` request header value,
the server will automatically send a `400` (Bad Request) HTTP error response
to the client and close the connection.
On top of this, it will emit an `error` event that can be used for logging
purposes like this:

```php
$server->on('error', function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
```

Note that the server will also emit an `error` event if you do not return a
valid response object from your request handler function. See also
[invalid response](#invalid-response) for more details.

### Response

The callback function passed to the constructor of the [`Server`](#server) or
advanced [`StreamingServer`](#server) is responsible for processing the request
and returning a response, which will be delivered to the client.
This function MUST return an instance implementing
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
$server = new Server(function (ServerRequestInterface $request) {
    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        "Hello World!\n"
    );
});
```

#### Deferred response

The example above returns the response directly, because it needs
no time to be processed.
Using a database, the file system or long calculations 
(in fact every action that will take >=1ms) to create your
response, will slow down the server.
To prevent this you SHOULD use a
[ReactPHP Promise](https://github.com/reactphp/promise#reactpromise).
This example shows how such a long-term action could look like:

```php
$server = new Server(function (ServerRequestInterface $request) use ($loop) {
    return new Promise(function ($resolve, $reject) use ($loop) {
        $loop->addTimer(1.5, function() use ($resolve) {
            $response = new Response(
                200,
                array(
                    'Content-Type' => 'text/plain'
                ),
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
If the client closes the connection while the promise is still pending, the
promise will automatically be cancelled.
The promise cancellation handler can be used to clean up any pending resources
allocated in this case (if applicable).
If a promise is resolved after the client closes, it will simply be ignored.

#### Streaming response

The `Response` class in this project supports to add an instance which implements the
[ReactPHP ReadableStreamInterface](https://github.com/reactphp/stream#readablestreaminterface)
for the response body.
So you are able stream data directly into the response body.
Note that other implementations of the `PSR-7 ResponseInterface` likely
only support strings.

```php
$server = new Server(function (ServerRequestInterface $request) use ($loop) {
    $stream = new ThroughStream();

    $timer = $loop->addPeriodicTimer(0.5, function () use ($stream) {
        $stream->write(microtime(true) . PHP_EOL);
    });

    $loop->addTimer(5, function() use ($loop, $timer, $stream) {
        $loop->cancelTimer($timer);
        $stream->end();
    });

    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        $stream
    );
});
```

The above example will emit every 0.5 seconds the current Unix timestamp 
with microseconds as float to the client and will end after 5 seconds.
This is just a example you could use of the streaming,
you could also send a big amount of data via little chunks 
or use it for body data that needs to calculated.

If the request handler resolves with a response stream that is already closed,
it will simply send an empty response body.
If the client closes the connection while the stream is still open, the
response stream will automatically be closed.
If a promise is resolved with a streaming body after the client closes, the
response stream will automatically be closed.
The `close` event can be used to clean up any pending resources allocated
in this case (if applicable).

> Note that special care has to be taken if you use a body stream instance that
  implements ReactPHP's
  [`DuplexStreamInterface`](https://github.com/reactphp/stream#duplexstreaminterface)
  (such as the `ThroughStream` in the above example).
>
> For *most* cases, this will simply only consume its readable side and forward
  (send) any data that is emitted by the stream, thus entirely ignoring the
  writable side of the stream.
  If however this is either a `101` (Switching Protocols) response or a `2xx`
  (Successful) response to a `CONNECT` method, it will also *write* data to the
  writable side of the stream.
  This can be avoided by either rejecting all requests with the `CONNECT`
  method (which is what most *normal* origin HTTP servers would likely do) or
  or ensuring that only ever an instance of `ReadableStreamInterface` is
  used.
>
> The `101` (Switching Protocols) response code is useful for the more advanced
  `Upgrade` requests, such as upgrading to the WebSocket protocol or
  implementing custom protocol logic that is out of scope of the HTTP specs and
  this HTTP library.
  If you want to handle the `Upgrade: WebSocket` header, you will likely want
  to look into using [Ratchet](http://socketo.me/) instead.
  If you want to handle a custom protocol, you will likely want to look into the
  [HTTP specs](https://tools.ietf.org/html/rfc7230#section-6.7) and also see
  [examples #31 and #32](examples) for more details.
  In particular, the `101` (Switching Protocols) response code MUST NOT be used
  unless you send an `Upgrade` response header value that is also present in
  the corresponding HTTP/1.1 `Upgrade` request header value.
  The server automatically takes care of sending a `Connection: upgrade`
  header value in this case, so you don't have to.
>
> The `CONNECT` method is useful in a tunneling setup (HTTPS proxy) and not
  something most origin HTTP servers would want to care about.
  The HTTP specs define an opaque "tunneling mode" for this method and make no
  use of the message body.
  For consistency reasons, this library uses a `DuplexStreamInterface` in the
  response body for tunneled application data.
  This implies that that a `2xx` (Successful) response to a `CONNECT` request
  can in fact use a streaming response body for the tunneled application data,
  so that any raw data the client sends over the connection will be piped
  through the writable stream for consumption.
  Note that while the HTTP specs make no use of the request body for `CONNECT`
  requests, one may still be present. Normal request body processing applies
  here and the connection will only turn to "tunneling mode" after the request
  body has been processed (which should be empty in most cases).
  See also [example #22](examples) for more details.

#### Response length

If the response body size is known, a `Content-Length` response header will be
added automatically. This is the most common use case, for example when using
a `string` response body like this:

```php 
$server = new Server(function (ServerRequestInterface $request) {
    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        "Hello World!\n"
    );
});
```

If the response body size is unknown, a `Content-Length` response header can not
be added automatically. When using a [streaming response](#streaming-response)
without an explicit `Content-Length` response header, outgoing HTTP/1.1 response
messages will automatically use `Transfer-Encoding: chunked` while legacy HTTP/1.0
response messages will contain the plain response body. If you know the length
of your streaming response body, you MAY want to specify it explicitly like this:

```php
$server = new Server(function (ServerRequestInterface $request) use ($loop) {
    $stream = new ThroughStream();

    $loop->addTimer(2.0, function () use ($stream) {
        $stream->end("Hello World!\n");
    });

    return new Response(
        200,
        array(
            'Content-Length' => '13',
            'Content-Type' => 'text/plain',
        ),
        $stream
    );
});
```

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

#### Invalid response

As stated above, each outgoing HTTP response is always represented by the
[PSR-7 `ResponseInterface`](https://www.php-fig.org/psr/psr-7/#33-psrhttpmessageresponseinterface).
If your request handler function returns an invalid value or throws an
unhandled `Exception` or `Throwable`, the server will automatically send a `500`
(Internal Server Error) HTTP error response to the client.
On top of this, it will emit an `error` event that can be used for logging
purposes like this:

```php
$server->on('error', function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    if ($e->getPrevious() !== null) {
        echo 'Previous: ' . $e->getPrevious()->getMessage() . PHP_EOL;
    }
});
```

Note that the server will also emit an `error` event if the client sends an
invalid HTTP request that never reaches your request handler function. See
also [invalid request](#invalid-request) for more details.
Additionally, a [streaming request](#streaming-request) body can also emit
an `error` event on the request body.

The server will only send a very generic `500` (Interval Server Error) HTTP
error response without any further details to the client if an unhandled
error occurs. While we understand this might make initial debugging harder,
it also means that the server does not leak any application details or stack
traces to the outside by default. It is usually recommended to catch any
`Exception` or `Throwable` within your request handler function or alternatively
use a [`middleware`](#middleware) to avoid this generic error handling and
create your own HTTP response message instead.

#### Default response headers

After the return in the callback function the response will be processed by the
[`Server`](#server) or [`StreamingServer`](#streamingserver) respectively.
They will add the protocol version of the request, so you don't have to.

A `Date` header will be automatically added with the system date and time if none is given.
You can add a custom `Date` header yourself like this:

```php
$server = new Server(function (ServerRequestInterface $request) {
    return new Response(
        200,
        array(
            'Date' => date('D, d M Y H:i:s T')
        )
    );
});
```

If you don't have a appropriate clock to rely on, you should
unset this header with an empty string:

```php
$server = new Server(function (ServerRequestInterface $request) {
    return new Response(
        200,
        array(
            'Date' => ''
        )
    );
});
```

Note that it will automatically assume a `X-Powered-By: react/alpha` header
unless your specify a custom `X-Powered-By` header yourself:

```php
$server = new Server(function (ServerRequestInterface $request) {
    return new Response(
        200,
        array(
            'X-Powered-By' => 'PHP 3'
        )
    );
});
```

If you do not want to send this header at all, you can use an empty string as
value like this:

```php
$server = new Server(function (ServerRequestInterface $request) {
    return new Response(
        200,
        array(
            'X-Powered-By' => ''
        )
    );
});
```

Note that persistent connections (`Connection: keep-alive`) are currently
not supported.
As such, HTTP/1.1 response messages will automatically include a
`Connection: close` header, irrespective of what header values are
passed explicitly.

### Middleware

As documented above, the [`Server`](#server) and advanced
[`StreamingServer`](#streamingserver) accept a single
request handler argument that is responsible for processing an incoming
HTTP request and then creating and returning an outgoing HTTP response.

Many common use cases involve validating, processing, manipulating the incoming
HTTP request before passing it to the final business logic request handler.
As such, this project supports the concept of middleware request handlers.

A middleware request handler is expected to adhere the following rules:

* It is a valid `callable`.
* It accepts `ServerRequestInterface` as first argument and an optional
  `callable` as second argument.
* It returns either:
  * An instance implementing `ResponseInterface` for direct consumption.
  * Any promise which can be consumed by
    [`Promise\resolve()`](https://reactphp.org/promise/#resolve) resolving to a
  `ResponseInterface` for deferred consumption.
  * It MAY throw an `Exception` (or return a rejected promise) in order to
    signal an error condition and abort the chain.
* It calls `$next($request)` to continue processing the next middleware
  request handler or returns explicitly without calling `$next` to
  abort the chain.
  * The `$next` request handler (recursively) invokes the next request
    handler from the chain with the same logic as above and returns (or throws)
    as above.
  * The `$request` may be modified prior to calling `$next($request)` to
    change the incoming request the next middleware operates on.
  * The `$next` return value may be consumed to modify the outgoing response.
  * The `$next` request handler MAY be called more than once if you want to
    implement custom "retry" logic etc.

Note that this very simple definition allows you to use either anonymous
functions or any classes that use the magic `__invoke()` method.
This allows you to easily create custom middleware request handlers on the fly
or use a class based approach to ease using existing middleware implementations.

While this project does provide the means to *use* middleware implementations,
it does not aim to *define* how middleware implementations should look like.
We realize that there's a vivid ecosystem of middleware implementations and
ongoing effort to standardize interfaces between these with
[PSR-15](https://www.php-fig.org/psr/psr-15/) (HTTP Server Request Handlers)
and support this goal.
As such, this project only bundles a few middleware implementations that are
required to match PHP's request behavior (see below) and otherwise actively
encourages [Third-Party Middleware](#third-party-middleware) implementations.

In order to use middleware request handlers, simply pass an array with all
callables as defined above to the [`Server`](#server) or
[`StreamingServer`](#streamingserver) respectively.
The following example adds a middleware request handler that adds the current time to the request as a 
header (`Request-Time`) and a final request handler that always returns a 200 code without a body: 

```php
$server = new Server(array(
    function (ServerRequestInterface $request, callable $next) {
        $request = $request->withHeader('Request-Time', time());
        return $next($request);
    },
    function (ServerRequestInterface $request) {
        return new Response(200);
    }
));
```

> Note how the middleware request handler and the final request handler have a
  very simple (and similar) interface. The only difference is that the final
  request handler does not receive a `$next` handler.

Similarly, you can use the result of the `$next` middleware request handler
function to modify the outgoing response.
Note that as per the above documentation, the `$next` middleware request handler may return a
`ResponseInterface` directly or one wrapped in a promise for deferred
resolution.
In order to simplify handling both paths, you can simply wrap this in a
[`Promise\resolve()`](https://reactphp.org/promise/#resolve) call like this:

```php
$server = new Server(array(
    function (ServerRequestInterface $request, callable $next) {
        $promise = React\Promise\resolve($next($request));
        return $promise->then(function (ResponseInterface $response) {
            return $response->withHeader('Content-Type', 'text/html');
        });
    },
    function (ServerRequestInterface $request) {
        return new Response(200);
    }
));
```

Note that the `$next` middleware request handler may also throw an
`Exception` (or return a rejected promise) as described above.
The previous example does not catch any exceptions and would thus signal an
error condition to the `Server`.
Alternatively, you can also catch any `Exception` to implement custom error
handling logic (or logging etc.) by wrapping this in a
[`Promise`](https://reactphp.org/promise/#promise) like this:

```php
$server = new Server(array(
    function (ServerRequestInterface $request, callable $next) {
        $promise = new React\Promise\Promise(function ($resolve) use ($next, $request) {
            $resolve($next($request));
        });
        return $promise->then(null, function (Exception $e) {
            return new Response(
                500,
                array(),
                'Internal error: ' . $e->getMessage()
            );
        });
    },
    function (ServerRequestInterface $request) {
        if (mt_rand(0, 1) === 1) {
            throw new RuntimeException('Database error');
        }
        return new Response(200);
    }
));
```

#### LimitConcurrentRequestsMiddleware

The `LimitConcurrentRequestsMiddleware` can be used to
limit how many next handlers can be executed concurrently.

If this middleware is invoked, it will check if the number of pending
handlers is below the allowed limit and then simply invoke the next handler
and it will return whatever the next handler returns (or throws).

If the number of pending handlers exceeds the allowed limit, the request will
be queued (and its streaming body will be paused) and it will return a pending
promise.
Once a pending handler returns (or throws), it will pick the oldest request
from this queue and invokes the next handler (and its streaming body will be
resumed).

The following example shows how this middleware can be used to ensure no more
than 10 handlers will be invoked at once:

```php
$server = new Server(array(
    new LimitConcurrentRequestsMiddleware(10),
    $handler
));
```

Similarly, this middleware is often used in combination with the
[`RequestBodyBufferMiddleware`](#requestbodybuffermiddleware) (see below)
to limit the total number of requests that can be buffered at once:

```php
$server = new StreamingServer(array(
    new LimitConcurrentRequestsMiddleware(100), // 100 concurrent buffering handlers
    new RequestBodyBufferMiddleware(2 * 1024 * 1024), // 2 MiB per request
    new RequestBodyParserMiddleware(),
    $handler
));
```

More sophisticated examples include limiting the total number of requests
that can be buffered at once and then ensure the actual request handler only
processes one request after another without any concurrency:

```php
$server = new StreamingServer(array(
    new LimitConcurrentRequestsMiddleware(100), // 100 concurrent buffering handlers
    new RequestBodyBufferMiddleware(2 * 1024 * 1024), // 2 MiB per request
    new RequestBodyParserMiddleware(),
    new LimitConcurrentRequestsMiddleware(1), // only execute 1 handler (no concurrency)
    $handler
));
```

#### RequestBodyBufferMiddleware

One of the built-in middleware is the `RequestBodyBufferMiddleware` which
can be used to buffer the whole incoming request body in memory.
This can be useful if full PSR-7 compatibility is needed for the request handler
and the default streaming request body handling is not needed.
The constructor accepts one optional argument, the maximum request body size.
When one isn't provided it will use `post_max_size` (default 8 MiB) from PHP's
configuration.
(Note that the value from your matching SAPI will be used, which is the CLI
configuration in most cases.)

Any incoming request that has a request body that exceeds this limit will be
accepted, but its request body will be discarded (empty request body).
This is done in order to avoid having to keep an incoming request with an
excessive size (for example, think of a 2 GB file upload) in memory.
This allows the next middleware handler to still handle this request, but it
will see an empty request body.
This is similar to PHP's default behavior, where the body will not be parsed
if this limit is exceeded. However, unlike PHP's default behavior, the raw
request body is not available via `php://input`.

The `RequestBodyBufferMiddleware` will buffer requests with bodies of known size 
(i.e. with `Content-Length` header specified) as well as requests with bodies of 
unknown size (i.e. with `Transfer-Encoding: chunked` header).

All requests will be buffered in memory until the request body end has
been reached and then call the next middleware handler with the complete,
buffered request.
Similarly, this will immediately invoke the next middleware handler for requests
that have an empty request body (such as a simple `GET` request) and requests
that are already buffered (such as due to another middleware).

Note that the given buffer size limit is applied to each request individually.
This means that if you allow a 2 MiB limit and then receive 1000 concurrent
requests, up to 2000 MiB may be allocated for these buffers alone.
As such, it's highly recommended to use this along with the
[`LimitConcurrentRequestsMiddleware`](#limitconcurrentrequestsmiddleware) (see above) to limit
the total number of concurrent requests.

Usage:

```php
$server = new StreamingServer(array(
    new LimitConcurrentRequestsMiddleware(100), // 100 concurrent buffering handlers
    new RequestBodyBufferMiddleware(16 * 1024 * 1024), // 16 MiB
    function (ServerRequestInterface $request) {
        // The body from $request->getBody() is now fully available without the need to stream it 
        return new Response(200);
    },
));
```

#### RequestBodyParserMiddleware

The `RequestBodyParserMiddleware` takes a fully buffered request body
(generally from [`RequestBodyBufferMiddleware`](#requestbodybuffermiddleware)), 
and parses the form values and file uploads from the incoming HTTP request body.

This middleware handler takes care of applying values from HTTP
requests that use `Content-Type: application/x-www-form-urlencoded` or
`Content-Type: multipart/form-data` to resemble PHP's default superglobals
`$_POST` and `$_FILES`.
Instead of relying on these superglobals, you can use the
`$request->getParsedBody()` and `$request->getUploadedFiles()` methods
as defined by PSR-7.

Accordingly, each file upload will be represented as instance implementing [`UploadedFileInterface`](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md#36-psrhttpmessageuploadedfileinterface).
Due to its blocking nature, the `moveTo()` method is not available and throws
a `RuntimeException` instead.
You can use `$contents = (string)$file->getStream();` to access the file
contents and persist this to your favorite data store.

```php
$handler = function (ServerRequestInterface $request) {
    // If any, parsed form fields are now available from $request->getParsedBody()
    $body = $request->getParsedBody();
    $name = isset($body['name']) ? $body['name'] : 'unnamed';

    $files = $request->getUploadedFiles();
    $avatar = isset($files['avatar']) ? $files['avatar'] : null;
    if ($avatar instanceof UploadedFileInterface) {
        if ($avatar->getError() === UPLOAD_ERR_OK) {
            $uploaded = $avatar->getSize() . ' bytes';
        } elseif ($avatar->getError() === UPLOAD_ERR_INI_SIZE) {
            $uploaded = 'file too large';
        } else {
            $uploaded = 'with error';
        }
    } else {
        $uploaded = 'nothing';
    }

    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        $name . ' uploaded ' . $uploaded
    );
};

$server = new StreamingServer(array((
    new LimitConcurrentRequestsMiddleware(100), // 100 concurrent buffering handlers
    new RequestBodyBufferMiddleware(16 * 1024 * 1024), // 16 MiB
    new RequestBodyParserMiddleware(),
    $handler
));
```

See also [example #12](examples) for more details.

By default, this middleware respects the
[`upload_max_filesize`](https://www.php.net/manual/en/ini.core.php#ini.upload-max-filesize)
(default `2M`) ini setting.
Files that exceed this limit will be rejected with an `UPLOAD_ERR_INI_SIZE` error.
You can control the maximum filesize for each individual file upload by
explicitly passing the maximum filesize in bytes as the first parameter to the
constructor like this:

```php
new RequestBodyParserMiddleware(8 * 1024 * 1024); // 8 MiB limit per file
```

By default, this middleware respects the
[`file_uploads`](https://www.php.net/manual/en/ini.core.php#ini.file-uploads)
(default `1`) and
[`max_file_uploads`](https://www.php.net/manual/en/ini.core.php#ini.max-file-uploads)
(default `20`) ini settings.
These settings control if any and how many files can be uploaded in a single request.
If you upload more files in a single request, additional files will be ignored
and the `getUploadedFiles()` method returns a truncated array.
Note that upload fields left blank on submission do not count towards this limit.
You can control the maximum number of file uploads per request by explicitly
passing the second parameter to the constructor like this:

```php
new RequestBodyParserMiddleware(10 * 1024, 100); // 100 files with 10 KiB each
```

> Note that this middleware handler simply parses everything that is already
  buffered in the request body.
  It is imperative that the request body is buffered by a prior middleware
  handler as given in the example above.
  This previous middleware handler is also responsible for rejecting incoming
  requests that exceed allowed message sizes (such as big file uploads).
  The [`RequestBodyBufferMiddleware`](#requestbodybuffermiddleware) used above
  simply discards excessive request bodies, resulting in an empty body.
  If you use this middleware without buffering first, it will try to parse an
  empty (streaming) body and may thus assume an empty data structure.
  See also [`RequestBodyBufferMiddleware`](#requestbodybuffermiddleware) for
  more details.
  
> PHP's `MAX_FILE_SIZE` hidden field is respected by this middleware.
  Files that exceed this limit will be rejected with an `UPLOAD_ERR_FORM_SIZE` error.

> This middleware respects the
  [`max_input_vars`](https://www.php.net/manual/en/info.configuration.php#ini.max-input-vars)
  (default `1000`) and
  [`max_input_nesting_level`](https://www.php.net/manual/en/info.configuration.php#ini.max-input-nesting-level)
  (default `64`) ini settings.

> Note that this middleware ignores the
  [`enable_post_data_reading`](https://www.php.net/manual/en/ini.core.php#ini.enable-post-data-reading)
  (default `1`) ini setting because it makes little sense to respect here and
  is left up to higher-level implementations.
  If you want to respect this setting, you have to check its value and
  effectively avoid using this middleware entirely.

#### Third-Party Middleware

While this project does provide the means to *use* middleware implementations
(see above), it does not aim to *define* how middleware implementations should
look like. We realize that there's a vivid ecosystem of middleware
implementations and ongoing effort to standardize interfaces between these with
[PSR-15](https://www.php-fig.org/psr/psr-15/) (HTTP Server Request Handlers)
and support this goal.
As such, this project only bundles a few middleware implementations that are
required to match PHP's request behavior (see above) and otherwise actively
encourages third-party middleware implementations.

While we would love to support PSR-15 directy in `react/http`, we understand
that this interface does not specifically target async APIs and as such does
not take advantage of promises for [deferred responses](#deferred-response).
The gist of this is that where PSR-15 enforces a `ResponseInterface` return
value, we also accept a `PromiseInterface<ResponseInterface>`.
As such, we suggest using the external
[PSR-15 middleware adapter](https://github.com/friends-of-reactphp/http-middleware-psr15-adapter)
that uses on the fly monkey patching of these return values which makes using
most PSR-15 middleware possible with this package without any changes required.

Other than that, you can also use the above [middleware definition](#middleware)
to create custom middleware. A non-exhaustive list of third-party middleware can
be found at the [middleware wiki](https://github.com/reactphp/http/wiki/Middleware).
If you build or know a custom middleware, make sure to let the world know and
feel free to add it to this list.

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require react/http:^0.8.7
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on legacy PHP 5.3 through current PHP 7+ and
HHVM.
It's *highly recommended to use PHP 7+* for this project.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
```

## License

MIT, see [LICENSE file](LICENSE).
