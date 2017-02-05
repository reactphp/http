<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Socket\ServerInterface as SocketServerInterface;
use React\Socket\ConnectionInterface;

/**
 * The `Server` class is responsible for handling incoming connections and then
 * emit a `request` event for each incoming HTTP request.
 *
 * ```php
 * $socket = new React\Socket\Server(8080, $loop);
 *
 * $http = new React\Http\Server($socket);
 * ```
 *
 * For each incoming connection, it emits a `request` event with the respective
 * [`Request`](#request) and [`Response`](#response) objects:
 *
 * ```php
 * $http->on('request', function (Request $request, Response $response) {
 *     $response->writeHead(200, array('Content-Type' => 'text/plain'));
 *     $response->end("Hello World!\n");
 * });
 * ```
 *
 * See also [`Request`](#request) and [`Response`](#response) for more details.
 *
 * @see Request
 * @see Response
 */
class Server extends EventEmitter
{
    private $io;

    /**
     * Creates a HTTP server that accepts connections from the given socket.
     *
     * It attaches itself to an instance of `React\Socket\ServerInterface` which
     * emits underlying streaming connections in order to then parse incoming data
     * as HTTP:
     *
     * ```php
     * $socket = new React\Socket\Server(8080, $loop);
     *
     * $http = new React\Http\Server($socket);
     * ```
     *
     * Similarly, you can also attach this to a
     * [`React\Socket\SecureServer`](https://github.com/reactphp/socket#secureserver)
     * in order to start a secure HTTPS server like this:
     *
     * ```php
     * $socket = new Server(8080, $loop);
     * $socket = new SecureServer($socket, $loop, array(
     *     'local_cert' => __DIR__ . '/localhost.pem'
     * ));
     *
     * $http = new React\Http\Server($socket);
     * ```
     *
     * @param \React\Socket\ServerInterface $io
     */
    public function __construct(SocketServerInterface $io)
    {
        $this->io = $io;
        $that = $this;

        $this->io->on('connection', function (ConnectionInterface $conn) use ($that) {
            // TODO: http 1.1 keep-alive
            // TODO: chunked transfer encoding (also for outgoing data)
            // TODO: multipart parsing

            $parser = new RequestHeaderParser();
            $parser->on('headers', function (Request $request, $bodyBuffer) use ($conn, $parser, $that) {
                // attach remote ip to the request as metadata
                $request->remoteAddress = trim(
                    parse_url('tcp://' . $conn->getRemoteAddress(), PHP_URL_HOST),
                    '[]'
                );

                // forward pause/resume calls to underlying connection
                $request->on('pause', array($conn, 'pause'));
                $request->on('resume', array($conn, 'resume'));

                $that->handleRequest($conn, $request, $bodyBuffer);

                $conn->removeListener('data', array($parser, 'feed'));
                $conn->on('end', function () use ($request) {
                    $request->emit('end');
                });
                $conn->on('data', function ($data) use ($request) {
                    $request->emit('data', array($data));
                });
            });

            $listener = array($parser, 'feed');
            $conn->on('data', $listener);
            $parser->on('error', function() use ($conn, $listener, $that) {
                // TODO: return 400 response
                $conn->removeListener('data', $listener);
                $that->emit('error', func_get_args());
            });
        });
    }

    /** @internal */
    public function handleRequest(ConnectionInterface $conn, Request $request, $bodyBuffer)
    {
        $response = new Response($conn);
        $response->on('close', array($request, 'close'));

        if (!$this->listeners('request')) {
            $response->end();

            return;
        }

        $this->emit('request', array($request, $response));

        if ($bodyBuffer !== '') {
            $request->emit('data', array($bodyBuffer));
        }
    }
}
