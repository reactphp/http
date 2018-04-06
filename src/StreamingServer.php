<?php

namespace React\Http;

use Evenement\EventEmitter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\ChunkedDecoder;
use React\Http\Io\ChunkedEncoder;
use React\Http\Io\CloseProtectionStream;
use React\Http\Io\HttpBodyStream;
use React\Http\Io\LengthLimitedStream;
use React\Http\Io\MiddlewareRunner;
use React\Http\Io\RequestHeaderParser;
use React\Http\Io\ServerRequest;
use React\Promise;
use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;

/**
 * The `Server` class is responsible for handling incoming connections and then
 * processing each incoming HTTP request.
 *
 * For each request, it executes the callback function passed to the
 * constructor with the respective [request](#request) object and expects
 * a respective [response](#response) object in return.
 *
 * ```php
 * $server = new StreamingServer(function (ServerRequestInterface $request) {
 *     return new Response(
 *         200,
 *         array('Content-Type' => 'text/plain'),
 *         "Hello World!\n"
 *     );
 * });
 * ```
 *
 * In order to process any connections, the server needs to be attached to an
 * instance of `React\Socket\ServerInterface` which emits underlying streaming
 * connections in order to then parse incoming data as HTTP.
 *
 * ```php
 * $socket = new React\Socket\Server(8080, $loop);
 * $server->listen($socket);
 * ```
 *
 * See also the [listen()](#listen) method and the [first example](examples) for more details.
 *
 * When HTTP/1.1 clients want to send a bigger request body, they MAY send only
 * the request headers with an additional `Expect: 100-continue` header and
 * wait before sending the actual (large) message body.
 * In this case the server will automatically send an intermediary
 * `HTTP/1.1 100 Continue` response to the client.
 * This ensures you will receive the request body without a delay as expected.
 * The [Response](#response) still needs to be created as described in the
 * examples above.
 *
 * See also [request](#request) and [response](#response)
 * for more details (e.g. the request data body).
 *
 * The `Server` supports both HTTP/1.1 and HTTP/1.0 request messages.
 * If a client sends an invalid request message, uses an invalid HTTP protocol
 * version or sends an invalid `Transfer-Encoding` in the request header, it will
 * emit an `error` event, send an HTTP error response to the client and
 * close the connection:
 *
 * ```php
 * $server->on('error', function (Exception $e) {
 *     echo 'Error: ' . $e->getMessage() . PHP_EOL;
 * });
 * ```
 *
 * Note that the request object can also emit an error.
 * Check out [request](#request) for more details.
 *
 * @see Request
 * @see Response
 * @see self::listen()
 */
final class StreamingServer extends EventEmitter
{
    private $callback;

    /**
     * Creates an HTTP server that invokes the given callback for each incoming HTTP request
     *
     * In order to process any connections, the server needs to be attached to an
     * instance of `React\Socket\ServerInterface` which emits underlying streaming
     * connections in order to then parse incoming data as HTTP.
     * See also [listen()](#listen) for more details.
     *
     * @param callable|callable[] $requestHandler
     * @see self::listen()
     */
    public function __construct($requestHandler)
    {
        if (!is_callable($requestHandler) && !is_array($requestHandler)) {
            throw new \InvalidArgumentException('Invalid request handler given');
        } elseif (!is_callable($requestHandler)) {
            $requestHandler = new MiddlewareRunner($requestHandler);
        }

        $this->callback = $requestHandler;
    }

    /**
     * Starts listening for HTTP requests on the given socket server instance
     *
     * The server needs to be attached to an instance of
     * `React\Socket\ServerInterface` which emits underlying streaming
     * connections in order to then parse incoming data as HTTP.
     * For each request, it executes the callback function passed to the
     * constructor with the respective [request](#request) object and expects
     * a respective [response](#response) object in return.
     *
     * You can attach this to a
     * [`React\Socket\Server`](https://github.com/reactphp/socket#server)
     * in order to start a plaintext HTTP server like this:
     *
     * ```php
     * $server = new StreamingServer($handler);
     *
     * $socket = new React\Socket\Server(8080, $loop);
     * $server->listen($socket);
     * ```
     *
     * See also [example #1](examples) for more details.
     *
     * Similarly, you can also attach this to a
     * [`React\Socket\SecureServer`](https://github.com/reactphp/socket#secureserver)
     * in order to start a secure HTTPS server like this:
     *
     * ```php
     * $server = new StreamingServer($handler);
     *
     * $socket = new React\Socket\Server(8080, $loop);
     * $socket = new React\Socket\SecureServer($socket, $loop, array(
     *     'local_cert' => __DIR__ . '/localhost.pem'
     * ));
     *
     * $server->listen($socket);
     * ```
     *
     * See also [example #11](examples) for more details.
     *
     * @param ServerInterface $socket
     */
    public function listen(ServerInterface $socket)
    {
        $socket->on('connection', array($this, 'handleConnection'));
    }

    /** @internal */
    public function handleConnection(ConnectionInterface $conn)
    {
        $uriLocal = $conn->getLocalAddress();
        if ($uriLocal !== null) {
            // local URI known, so translate transport scheme to application scheme
            $uriLocal = strtr($uriLocal, array('tcp://' => 'http://', 'tls://' => 'https://'));
        }

        $uriRemote = $conn->getRemoteAddress();

        $that = $this;
        $parser = new RequestHeaderParser($uriLocal, $uriRemote);

        $listener = array($parser, 'feed');
        $parser->on('headers', function (ServerRequestInterface $request, $bodyBuffer) use ($conn, $listener, $that) {
            // parsing request completed => stop feeding parser
            $conn->removeListener('data', $listener);

            $that->handleRequest($conn, $request);

            if ($bodyBuffer !== '') {
                $conn->emit('data', array($bodyBuffer));
            }
        });

        $conn->on('data', $listener);
        $parser->on('error', function(\Exception $e) use ($conn, $listener, $that) {
            $conn->removeListener('data', $listener);
            $that->emit('error', array($e));

            $that->writeError(
                $conn,
                $e->getCode() !== 0 ? $e->getCode() : 400
            );
        });
    }

    /** @internal */
    public function handleRequest(ConnectionInterface $conn, ServerRequestInterface $request)
    {
        $contentLength = 0;
        $stream = new CloseProtectionStream($conn);
        if ($request->hasHeader('Transfer-Encoding')) {
            if (strtolower($request->getHeaderLine('Transfer-Encoding')) !== 'chunked') {
                $this->emit('error', array(new \InvalidArgumentException('Only chunked-encoding is allowed for Transfer-Encoding')));
                return $this->writeError($conn, 501, $request);
            }

            // Transfer-Encoding: chunked and Content-Length header MUST NOT be used at the same time
            // as per https://tools.ietf.org/html/rfc7230#section-3.3.3
            if ($request->hasHeader('Content-Length')) {
                $this->emit('error', array(new \InvalidArgumentException('Using both `Transfer-Encoding: chunked` and `Content-Length` is not allowed')));
                return $this->writeError($conn, 400, $request);
            }

            $stream = new ChunkedDecoder($stream);
            $contentLength = null;
        } elseif ($request->hasHeader('Content-Length')) {
            $string = $request->getHeaderLine('Content-Length');

            $contentLength = (int)$string;
            if ((string)$contentLength !== $string) {
                // Content-Length value is not an integer or not a single integer
                $this->emit('error', array(new \InvalidArgumentException('The value of `Content-Length` is not valid')));
                return $this->writeError($conn, 400, $request);
            }

            $stream = new LengthLimitedStream($stream, $contentLength);
        }

        $request = $request->withBody(new HttpBodyStream($stream, $contentLength));

        if ($request->getProtocolVersion() !== '1.0' && '100-continue' === strtolower($request->getHeaderLine('Expect'))) {
            $conn->write("HTTP/1.1 100 Continue\r\n\r\n");
        }

        // execute request handler callback
        $callback = $this->callback;
        try {
            $response = $callback($request);
        } catch (\Exception $error) {
            // request handler callback throws an Exception
            $response = Promise\reject($error);
        } catch (\Throwable $error) { // @codeCoverageIgnoreStart
            // request handler callback throws a PHP7+ Error
            $response = Promise\reject($error); // @codeCoverageIgnoreEnd
        }

        // cancel pending promise once connection closes
        if ($response instanceof CancellablePromiseInterface) {
            $conn->on('close', function () use ($response) {
                $response->cancel();
            });
        }

        // happy path: request body is known to be empty => immediately end stream
        if ($contentLength === 0) {
            $stream->emit('end');
            $stream->close();
        }

        // happy path: response returned, handle and return immediately
        if ($response instanceof ResponseInterface) {
            return $this->handleResponse($conn, $request, $response);
        }

        // did not return a promise? this is an error, convert into one for rejection below.
        if (!$response instanceof PromiseInterface) {
            $response = Promise\resolve($response);
        }

        $that = $this;
        $response->then(
            function ($response) use ($that, $conn, $request) {
                if (!$response instanceof ResponseInterface) {
                    $message = 'The response callback is expected to resolve with an object implementing Psr\Http\Message\ResponseInterface, but resolved with "%s" instead.';
                    $message = sprintf($message, is_object($response) ? get_class($response) : gettype($response));
                    $exception = new \RuntimeException($message);

                    $that->emit('error', array($exception));
                    return $that->writeError($conn, 500, $request);
                }
                $that->handleResponse($conn, $request, $response);
            },
            function ($error) use ($that, $conn, $request) {
                $message = 'The response callback is expected to resolve with an object implementing Psr\Http\Message\ResponseInterface, but rejected with "%s" instead.';
                $message = sprintf($message, is_object($error) ? get_class($error) : gettype($error));

                $previous = null;

                if ($error instanceof \Throwable || $error instanceof \Exception) {
                    $previous = $error;
                }

                $exception = new \RuntimeException($message, null, $previous);

                $that->emit('error', array($exception));
                return $that->writeError($conn, 500, $request);
            }
        );
    }

    /** @internal */
    public function writeError(ConnectionInterface $conn, $code, ServerRequestInterface $request = null)
    {
        $response = new Response(
            $code,
            array(
                'Content-Type' => 'text/plain'
            ),
            'Error ' . $code
        );

        // append reason phrase to response body if known
        $reason = $response->getReasonPhrase();
        if ($reason !== '') {
            $body = $response->getBody();
            $body->seek(0, SEEK_END);
            $body->write(': ' . $reason);
        }

        if ($request === null) {
            $request = new ServerRequest('GET', '/', array(), null, '1.1');
        }

        $this->handleResponse($conn, $request, $response);
    }


    /** @internal */
    public function handleResponse(ConnectionInterface $connection, ServerRequestInterface $request, ResponseInterface $response)
    {
        // return early and close response body if connection is already closed
        $body = $response->getBody();
        if (!$connection->isWritable()) {
            $body->close();
            return;
        }

        $response = $response->withProtocolVersion($request->getProtocolVersion());

        // assign default "X-Powered-By" header as first for history reasons
        if (!$response->hasHeader('X-Powered-By')) {
            $response = $response->withHeader('X-Powered-By', 'React/alpha');
        }

        if ($response->hasHeader('X-Powered-By') && $response->getHeaderLine('X-Powered-By') === ''){
            $response = $response->withoutHeader('X-Powered-By');
        }

        $response = $response->withoutHeader('Transfer-Encoding');

        // assign date header if no 'date' is given, use the current time where this code is running
        if (!$response->hasHeader('Date')) {
            // IMF-fixdate  = day-name "," SP date1 SP time-of-day SP GMT
            $response = $response->withHeader('Date', gmdate('D, d M Y H:i:s') . ' GMT');
        }

        if ($response->hasHeader('Date') && $response->getHeaderLine('Date') === ''){
            $response = $response->withoutHeader('Date');
        }

        if (!$body instanceof HttpBodyStream) {
            $response = $response->withHeader('Content-Length', (string)$body->getSize());
        } elseif (!$response->hasHeader('Content-Length') && $request->getProtocolVersion() === '1.1') {
            // assign chunked transfer-encoding if no 'content-length' is given for HTTP/1.1 responses
            $response = $response->withHeader('Transfer-Encoding', 'chunked');
        }

        // HTTP/1.1 assumes persistent connection support by default
        // we do not support persistent connections, so let the client know
        if ($request->getProtocolVersion() === '1.1') {
            $response = $response->withHeader('Connection', 'close');
        }
        // 2xx response to CONNECT and 1xx and 204 MUST NOT include Content-Length or Transfer-Encoding header
        $code = $response->getStatusCode();
        if (($request->getMethod() === 'CONNECT' && $code >= 200 && $code < 300) || ($code >= 100 && $code < 200) || $code === 204) {
            $response = $response->withoutHeader('Content-Length')->withoutHeader('Transfer-Encoding');
        }

        // 101 (Switching Protocols) response uses Connection: upgrade header
        // persistent connections are currently not supported, so do not use
        // this for any other replies in order to preserve "Connection: close"
        if ($code === 101) {
            $response = $response->withHeader('Connection', 'upgrade');
        }

        // 101 (Switching Protocols) response (for Upgrade request) forwards upgraded data through duplex stream
        // 2xx (Successful) response to CONNECT forwards tunneled application data through duplex stream
        if (($code === 101 || ($request->getMethod() === 'CONNECT' && $code >= 200 && $code < 300)) && $body instanceof HttpBodyStream && $body->input instanceof WritableStreamInterface) {
            if ($request->getBody()->isReadable()) {
                // request is still streaming => wait for request close before forwarding following data from connection
                $request->getBody()->on('close', function () use ($connection, $body) {
                    if ($body->input->isWritable()) {
                        $connection->pipe($body->input);
                        $connection->resume();
                    }
                });
            } elseif ($body->input->isWritable()) {
                // request already closed => forward following data from connection
                $connection->pipe($body->input);
                $connection->resume();
            }
        }

        // build HTTP response header by appending status line and header fields
        $headers = "HTTP/" . $response->getProtocolVersion() . " " . $response->getStatusCode() . " " . $response->getReasonPhrase() . "\r\n";
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers .= $name . ": " . $value . "\r\n";
            }
        }

        // response to HEAD and 1xx, 204 and 304 responses MUST NOT include a body
        // exclude status 101 (Switching Protocols) here for Upgrade request handling above
        if ($request->getMethod() === 'HEAD' || $code === 100 || ($code > 101 && $code < 200) || $code === 204 || $code === 304) {
            $body = '';
        }

        // this is a non-streaming response body or the body stream already closed?
        if (!$body instanceof ReadableStreamInterface || !$body->isReadable()) {
            // add final chunk if a streaming body is already closed and uses `Transfer-Encoding: chunked`
            if ($body instanceof ReadableStreamInterface && $response->getHeaderLine('Transfer-Encoding') === 'chunked') {
                $body = "0\r\n\r\n";
            }

            // end connection after writing response headers and body
            $connection->write($headers . "\r\n" . $body);
            $connection->end();
            return;
        }

        $connection->write($headers . "\r\n");

        if ($response->getHeaderLine('Transfer-Encoding') === 'chunked') {
            $body = new ChunkedEncoder($body);
        }

        // Close response stream once connection closes.
        // Note that this TCP/IP close detection may take some time,
        // in particular this may only fire on a later read/write attempt.
        $connection->on('close', array($body, 'close'));

        $body->pipe($connection);
    }
}
