<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Socket\ServerInterface as SocketServerInterface;
use React\Socket\ConnectionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RingCentral;
use React\Stream\ReadableStream;
use React\Promise\Promise;

/**
 * The `Server` class is responsible for handling incoming connections and then
 * processing each incoming HTTP request.
 *
 * It attaches itself to an instance of `React\Socket\ServerInterface` which
 * emits underlying streaming connections in order to then parse incoming data
 * as HTTP.
 *
 * For each request, it executes the callback function passed to the
 * constructor with the respective [`Request`](#request) and
 * [`Response`](#response) objects:
 *
 * ```php
 * $socket = new React\Socket\Server(8080, $loop);
 *
 * $http = new Server($socket, function (RequestInterface $request, Response $response) {
 *     $response->writeHead(200, array('Content-Type' => 'text/plain'));
 *     $response->end("Hello World!\n");
 * });
 * ```
 *
 * Similarly, you can also attach this to a
 * [`React\Socket\SecureServer`](https://github.com/reactphp/socket#secureserver)
 * in order to start a secure HTTPS server like this:
 *
 * ```php
 * $socket = new React\Socket\Server(8080, $loop);
 * $socket = new React\Socket\SecureServer($socket, $loop, array(
 *     'local_cert' => __DIR__ . '/localhost.pem'
 * ));
 *
 * $http = new Server($socket, function (RequestInterface $request, Response $response) {
 *     $response->writeHead(200, array('Content-Type' => 'text/plain'));
 *     $response->end("Hello World!\n");
 * });
 * ```
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
 * See also [`Request`](#request) and [`Response`](#response)
 * for more details(e.g. the request data body).
 *
 * The `Server` supports both HTTP/1.1 and HTTP/1.0 request messages.
 * If a client sends an invalid request message, uses an invalid HTTP protocol
 * version or sends an invalid `Transfer-Encoding` in the request header, it will
 * emit an `error` event, send an HTTP error response to the client and
 * close the connection:
 *
 * ```php
 * $http->on('error', function (Exception $e) {
 *     echo 'Error: ' . $e->getMessage() . PHP_EOL;
 * });
 * ```
 *
 * The request object can also emit an error. Checkout [Request](#request)
 * for more details.
 *
 * @see Request
 * @see Response
 */
class Server extends EventEmitter
{
    private $callback;

    /**
     * Creates a HTTP server that accepts connections from the given socket.
     *
     * It attaches itself to an instance of `React\Socket\ServerInterface` which
     * emits underlying streaming connections in order to then parse incoming data
     * as HTTP.
     *
     * For each request, it executes the callback function passed to the
     * constructor with the respective [`Request`](#request) and
     * [`Response`](#response) objects:
     *
     * ```php
     * $socket = new React\Socket\Server(8080, $loop);
     *
     * $http = new Server($socket, function (Request $request, Response $response) {
     *     $response->writeHead(200, array('Content-Type' => 'text/plain'));
     *     $response->end("Hello World!\n");
     * });
     * ```
     *
     * Similarly, you can also attach this to a
     * [`React\Socket\SecureServer`](https://github.com/reactphp/socket#secureserver)
     * in order to start a secure HTTPS server like this:
     *
     * ```php
     * $socket = new React\Socket\Server(8080, $loop);
     * $socket = new React\Socket\SecureServer($socket, $loop, array(
     *     'local_cert' => __DIR__ . '/localhost.pem'
     * ));
     *
     * $http = new Server($socket, function (Request $request, Response $response) {
     *    $response->writeHead(200, array('Content-Type' => 'text/plain'));
     *    $response->end("Hello World!\n");
     * });
     *```
     *
     * @param \React\Socket\ServerInterface $io
     * @param callable $callback
     */
    public function __construct(SocketServerInterface $io, $callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException();
        }

        $io->on('connection', array($this, 'handleConnection'));
        $this->callback = $callback;
    }

    /** @internal */
    public function handleConnection(ConnectionInterface $conn)
    {
        $that = $this;
        $parser = new RequestHeaderParser();
        $listener = array($parser, 'feed');
        $parser->on('headers', function (RequestInterface $request, $bodyBuffer) use ($conn, $listener, $parser, $that) {
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
                ($e instanceof \OverflowException) ? 431 : 400
            );
        });
    }

    /** @internal */
    public function handleRequest(ConnectionInterface $conn, RequestInterface $request)
    {
        // only support HTTP/1.1 and HTTP/1.0 requests
        if ($request->getProtocolVersion() !== '1.1' && $request->getProtocolVersion() !== '1.0') {
            $this->emit('error', array(new \InvalidArgumentException('Received request with invalid protocol version')));
            return $this->writeError($conn, 505);
        }

        // HTTP/1.1 requests MUST include a valid host header (host and optional port)
        // https://tools.ietf.org/html/rfc7230#section-5.4
        if ($request->getProtocolVersion() === '1.1') {
            $parts = parse_url('http://' . $request->getHeaderLine('Host'));

            // make sure value contains valid host component (IP or hostname)
            if (!$parts || !isset($parts['scheme'], $parts['host'])) {
                $parts = false;
            }

            // make sure value does not contain any other URI component
            unset($parts['scheme'], $parts['host'], $parts['port']);
            if ($parts === false || $parts) {
                $this->emit('error', array(new \InvalidArgumentException('Invalid Host header for HTTP/1.1 request')));
                return $this->writeError($conn, 400);
            }
        }

        $contentLength = 0;
        $stream = new CloseProtectionStream($conn);
        if ($request->hasHeader('Transfer-Encoding')) {

            if (strtolower($request->getHeaderLine('Transfer-Encoding')) !== 'chunked') {
                $this->emit('error', array(new \InvalidArgumentException('Only chunked-encoding is allowed for Transfer-Encoding')));
                return $this->writeError($conn, 501);
            }

            $stream = new ChunkedDecoder($stream);

            $request = $request->withoutHeader('Transfer-Encoding');
            $request = $request->withoutHeader('Content-Length');

            $contentLength = null;
        } elseif ($request->hasHeader('Content-Length')) {
            $string = $request->getHeaderLine('Content-Length');

            $contentLength = (int)$string;
            if ((string)$contentLength !== (string)$string) {
                // Content-Length value is not an integer or not a single integer
                $this->emit('error', array(new \InvalidArgumentException('The value of `Content-Length` is not valid')));
                return $this->writeError($conn, 400);
            }

            $stream = new LengthLimitedStream($stream, $contentLength);
        }

        $request = $request->withBody(new HttpBodyStream($stream, $contentLength));

        if ($request->getProtocolVersion() !== '1.0' && '100-continue' === strtolower($request->getHeaderLine('Expect'))) {
            $conn->write("HTTP/1.1 100 Continue\r\n\r\n");
        }

        // attach remote ip to the request as metadata
        $request->remoteAddress = trim(
            parse_url('tcp://' . $conn->getRemoteAddress(), PHP_URL_HOST),
            '[]'
        );

        $callback = $this->callback;
        $promise = new Promise(function ($resolve, $reject) use ($callback, $request) {
            $resolve($callback($request));
        });

        $that = $this;
        $promise->then(
            function ($response) use ($that, $conn, $request) {
                if (!$response instanceof ResponseInterface) {
                    $message = 'The response callback is expected to resolve with an object implementing Psr\Http\Message\ResponseInterface, but resolved with "%s" instead.';
                    $message = sprintf($message, is_object($response) ? get_class($response) : gettype($response));
                    $exception = new \RuntimeException($message);

                    $that->emit('error', array($exception));
                    return $that->writeError($conn, 500);
                }
                $that->handleResponse($conn, $response, $request->getProtocolVersion());
            },
            function ($error) use ($that, $conn) {
                $message = 'The response callback is expected to resolve with an object implementing Psr\Http\Message\ResponseInterface, but rejected with "%s" instead.';
                $message = sprintf($message, is_object($error) ? get_class($error) : gettype($error));
                $exception = new \RuntimeException($message, null, $error instanceof \Exception ? $error : null);

                $that->emit('error', array($exception));
                return $that->writeError($conn, 500);
            }
        );

        if ($contentLength === 0) {
            // If Body is empty or Content-Length is 0 and won't emit further data,
            // 'data' events from other streams won't be called anymore
            $stream->emit('end');
            $stream->close();
        }
    }

    /** @internal */
    public function writeError(ConnectionInterface $conn, $code)
    {
        $message = 'Error ' . $code;
        if (isset(ResponseCodes::$statusTexts[$code])) {
            $message .= ': ' . ResponseCodes::$statusTexts[$code];
        }

        $response = new Response(
            $code,
            array(
                'Content-Type' => 'text/plain'
            ),
            $message
        );

        $this->handleResponse($conn, $response, '1.1');
    }


    /** @internal */
    public function handleResponse(ConnectionInterface $connection, ResponseInterface $response, $protocolVersion)
    {
        $response = $response->withProtocolVersion($protocolVersion);

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

        if (!$response->getBody() instanceof HttpBodyStream) {
            $response = $response->withHeader('Content-Length', $response->getBody()->getSize());
        } elseif (!$response->hasHeader('Content-Length') && $protocolVersion === '1.1') {
            // assign chunked transfer-encoding if no 'content-length' is given for HTTP/1.1 responses
            $response = $response->withHeader('Transfer-Encoding', 'chunked');
        }

        // HTTP/1.1 assumes persistent connection support by default
        // we do not support persistent connections, so let the client know
        if ($protocolVersion === '1.1') {
            $response = $response->withHeader('Connection', 'close');
        }

        $this->handleResponseBody($response, $connection);
    }

    private function handleResponseBody(ResponseInterface $response, ConnectionInterface $connection)
    {
        if (!$response->getBody() instanceof HttpBodyStream) {
            $connection->write(RingCentral\Psr7\str($response));
            return $connection->end();
        }

        $body = $response->getBody();
        $stream = $body;

        if ($response->getHeaderLine('Transfer-Encoding') === 'chunked') {
            $stream = new ChunkedEncoder($body);
        }

        $connection->write(RingCentral\Psr7\str($response));
        $stream->pipe($connection);
    }
}
