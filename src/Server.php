<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Middleware\RequestBodyParserMiddleware;
use React\Socket\ServerInterface;

/**
 * @see StreamingServer
 * @see Request
 * @see Response
 * @see self::listen()
 */
final class Server extends EventEmitter
{
    /**
     * @var StreamingServer
     */
    private $server;

    /**
     * Creates a facade around StreamingServer with request body buffering
     * and parsing middleware.
     *
     * @param callable $callback
     * @see StreamingServer::__construct()
     * @see self::listen()
     */
    public function __construct($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException();
        }

        $that = $this;
        $this->server = new StreamingServer(new MiddlewareRunner(array(
            new RequestBodyBufferMiddleware(),
            new RequestBodyParserMiddleware(),
            $callback,
        )));
        $this->server->on('error', function ($error) use ($that) {
            $that->emit('error', $error);
        });
    }

    /**
     * @param ServerInterface $socket
     * @see StreamingServer::listen()
     */
    public function listen(ServerInterface $socket)
    {
        $this->server->listen($socket);
    }
}
