<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Socket\ServerInterface as SocketServerInterface;
use React\Socket\ConnectionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\Promise\Promise;
use RingCentral\Psr7 as Psr7Implementation;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The `Server` class is responsible for handling incoming connections and then
 * processing each incoming HTTP request.
 *
 * It attaches itself to an instance of `React\Socket\ServerInterface` which
 * emits underlying streaming connections in order to then parse incoming data
 * as HTTP.
 *
 * For each request, it executes the callback function passed to the
 * constructor with the respective [request](#request) and
 * [response](#response) objects:
 *
 * ```php
 * $socket = new React\Socket\Server(8080, $loop);
 *
 * $http = new Server($socket, function (RequestInterface $request) {
 *     return new Response(
 *         200,
 *         array('Content-Type' => 'text/plain'),
 *         "Hello World!\n"
 *     );
 * });
 * ```
 *
 * See also the [first example](examples) for more details.
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
 * $http = new Server($socket, function (RequestInterface $request) {
 *     return new Response(
 *         200,
 *         array('Content-Type' => 'text/plain'),
 *         "Hello World!\n"
 *     );
 * });
 * ```
 *
 * See also [example #11](examples) for more details.
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
 * $http->on('error', function (Exception $e) {
 *     echo 'Error: ' . $e->getMessage() . PHP_EOL;
 * });
 * ```
 *
 * Note that the request object can also emit an error.
 * Check out [request](#request) for more details.
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
        $parser = new RequestHeaderParser(
            ($this->isConnectionEncrypted($conn) ? 'https://' : 'http://') . $conn->getLocalAddress()
        );

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
                $e->getCode() !== 0 ? $e->getCode() : 400
            );
        });
    }

    /** @internal */
    public function handleRequest(ConnectionInterface $conn, ServerRequestInterface $request)
    {
        $contentLength = 0;
        $stream = new CloseProtectionStream($conn);
        if ($request->getMethod() === 'CONNECT') {
            // CONNECT method MUST use authority-form request target
            $parts = parse_url('tcp://' . $request->getRequestTarget());
            if (!isset($parts['scheme'], $parts['host'], $parts['port']) || count($parts) !== 3) {
                $this->emit('error', array(new \InvalidArgumentException('CONNECT method MUST use authority-form request target')));
                return $this->writeError($conn, 400);
            }

            // CONNECT uses undelimited body until connection closes
            $request = $request->withoutHeader('Transfer-Encoding');
            $request = $request->withoutHeader('Content-Length');
            $contentLength = null;

            // emit end event before the actual close event
            $stream->on('close', function () use ($stream) {
                $stream->emit('end');
            });
        } else if ($request->hasHeader('Transfer-Encoding')) {

            if (strtolower($request->getHeaderLine('Transfer-Encoding')) !== 'chunked') {
                $this->emit('error', array(new \InvalidArgumentException('Only chunked-encoding is allowed for Transfer-Encoding')));
                return $this->writeError($conn, 501, $request);
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
                return $this->writeError($conn, 400, $request);
            }

            $stream = new LengthLimitedStream($stream, $contentLength);
        }

        $upgradeRequest = false;
        if ($request->getProtocolVersion() !== '1.0' && $request->hasHeader('Connection') && strtolower($request->getHeaderLine('Connection')) === "upgrade") {
            if (!$request->hasHeader('Upgrade') || $request->getHeaderLine('Upgrade') === '') {
                // MUST have Upgrade options
                $this->emit('error', array(new \InvalidArgumentException('Connection upgrade must specify upgrade protocol.')));
                return $this->writeError($conn, 400, $request);
            }
            $upgradeRequest = true;
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
            function ($response) use ($that, $conn, $request, $contentLength, $stream, $upgradeRequest) {
                if (!$response instanceof ResponseInterface) {
                    $message = 'The response callback is expected to resolve with an object implementing Psr\Http\Message\ResponseInterface, but resolved with "%s" instead.';
                    $message = sprintf($message, is_object($response) ? get_class($response) : gettype($response));
                    $exception = new \RuntimeException($message);

                    $that->emit('error', array($exception));
                    return $that->writeError($conn, 500, $request);
                }

                if ($response->getStatusCode() === 426) {
                    if (!$response->hasHeader('Upgrade') || $response->getHeaderLine('Upgrade') === '') {
                        $message = 'HTTP 1.1 426 response requires `Upgrade` header.';
                        $exception = new \RuntimeException($message);

                        $that->emit('error', array($exception));
                        return $that->writeError($conn, 500, $request);
                    }
                }

                $upgradeConnection = false;
                if ($response->getStatusCode() === 101) {
                    if (!$upgradeRequest) {
                        $message = 'HTTP status 101 is not valid when no upgrade was requested';
                        $exception = new \RuntimeException($message);

                        $that->emit('error', array($exception));
                        return $that->writeError($conn, 500, $request);
                    }

                    if ($response->getProtocolVersion() === '1.0') {
                        $message = 'HTTP status 101 is not valid with protocol version 1.0';
                        $exception = new \RuntimeException($message);

                        $that->emit('error', array($exception));
                        return $that->writeError($conn, 500, $request);
                    }

                    if (!$response->hasHeader('Connection') || strtolower($response->getHeaderLine('Connection')) !== 'upgrade') {
                        $message = 'HTTP 1.1 Upgrade requires `Connection: upgrade` header.';
                        $exception = new \RuntimeException($message);

                        $that->emit('error', array($exception));
                        return $that->writeError($conn, 500, $request);
                    }

                    if (!$response->hasHeader('Upgrade') || $response->getHeaderLine('Upgrade') === '') {
                        $message = 'HTTP 1.1 Upgrade requires `Upgrade` header with exactly one protocol specified.';
                        $exception = new \RuntimeException($message);

                        $that->emit('error', array($exception));
                        return $that->writeError($conn, 500, $request);
                    }

                    $requestedProtocols = explode(',', preg_replace('/\s+/', '', $request->getHeaderLine('Upgrade')));

                    if (!in_array(trim($response->getHeaderLine('Upgrade')), $requestedProtocols)) {
                        $message = 'Upgrade requires response protocol to be one of the `Upgrade` protocols specified by the request.';
                        $exception = new \RuntimeException($message);

                        $that->emit('error', array($exception));
                        return $that->writeError($conn, 500, $request);
                    }

                    $upgradeConnection = true;
                }

                if (!$upgradeConnection && $contentLength === 0) {
                    // If Body is empty or Content-Length is 0 and won't emit further data,
                    // 'data' events from other streams won't be called anymore
                    $stream->emit('end');
                    $stream->close();
                }

                $that->handleResponse($conn, $request, $response);
            },
            function ($error) use ($that, $conn, $request) {
                $message = 'The response callback is expected to resolve with an object implementing Psr\Http\Message\ResponseInterface, but rejected with "%s" instead.';
                $message = sprintf($message, is_object($error) ? get_class($error) : gettype($error));
                $exception = new \RuntimeException($message, null, $error instanceof \Exception ? $error : null);

                $that->emit('error', array($exception));
                return $that->writeError($conn, 500, $request);
            }
        );
    }

    /** @internal */
    public function writeError(ConnectionInterface $conn, $code, ServerRequestInterface $request = null)
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

        if ($request === null) {
            $request = new ServerRequest('GET', '/', array(), null, '1.1');
        }

        $this->handleResponse($conn, $request, $response);
    }


    /** @internal */
    public function handleResponse(ConnectionInterface $connection, ServerRequestInterface $request, ResponseInterface $response)
    {
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

        if (!$response->getBody() instanceof HttpBodyStream) {
            $response = $response->withHeader('Content-Length', (string)$response->getBody()->getSize());
        } elseif (!$response->hasHeader('Content-Length') && $request->getProtocolVersion() === '1.1') {
            // assign chunked transfer-encoding if no 'content-length' is given for HTTP/1.1 responses
            $response = $response->withHeader('Transfer-Encoding', 'chunked');
        }

        // HTTP/1.1 assumes persistent connection support by default
        // we do not support persistent connections, so let the client know
        if ($request->getProtocolVersion() === '1.1' && $response->getStatusCode() !== 101) {
            $response = $response->withHeader('Connection', 'close');
        }

        // 2xx response to CONNECT and 1xx and 204 MUST NOT include Content-Length or Transfer-Encoding header
        $code = $response->getStatusCode();
        if (($request->getMethod() === 'CONNECT' && $code >= 200 && $code < 300) || ($code >= 100 && $code < 200) || $code === 204) {
            $response = $response->withoutHeader('Content-Length')->withoutHeader('Transfer-Encoding');
        }

        // 101 response (Upgrade) should hold onto the body
        if ($code !== 101) {
            // response to HEAD and 1xx, 204 and 304 responses MUST NOT include a body
            if ($request->getMethod() === 'HEAD' || ($code >= 100 && $code < 200) || $code === 204 || $code === 304) {
                $response = $response->withBody(Psr7Implementation\stream_for(''));
            }
        }

        $this->handleResponseBody($response, $connection);
    }

    private function handleResponseBody(ResponseInterface $response, ConnectionInterface $connection)
    {
        if (!$response->getBody() instanceof HttpBodyStream) {
            $connection->write(Psr7Implementation\str($response));
            return $connection->end();
        }

        $body = $response->getBody();
        $stream = $body;

        if ($response->getHeaderLine('Transfer-Encoding') === 'chunked') {
            $stream = new ChunkedEncoder($body);
        }

        $connection->write(Psr7Implementation\str($response));
        $stream->pipe($connection);
    }

    /**
     * @param ConnectionInterface $conn
     * @return bool
     * @codeCoverageIgnore
     */
    private function isConnectionEncrypted(ConnectionInterface $conn)
    {
        // Legacy PHP < 7 does not offer any direct access to check crypto parameters
        // We work around by accessing the context options and assume that only
        // secure connections *SHOULD* set the "ssl" context options by default.
        if (PHP_VERSION_ID < 70000) {
            $context = isset($conn->stream) ? stream_context_get_options($conn->stream) : array();

            return (isset($context['ssl']) && $context['ssl']);
        }

        // Modern PHP 7+ offers more reliable access to check crypto parameters
        // by checking stream crypto meta data that is only then made available.
        $meta = isset($conn->stream) ? stream_get_meta_data($conn->stream) : array();

        return (isset($meta['crypto']) && $meta['crypto']);
    }
}
