<?php

namespace React\Http;

use Evenement\EventEmitter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Http\Psr\Http\MiddlewareStackHandler;
use React\Socket\ServerInterface;

/**
 * A HTTP server which can handle PSR-15 compatible handlers and middlewares.
 */
final class PsrHttpServer extends EventEmitter
{
    /**
     * @var MiddlewareInterface
     */
    private $middlewareStack = array();

    /**
     * @var RequestHandlerInterface
     */
    private $handler;

    /**
     * @var Server
     */
    private $server;

    /**
     * @param RequestHandlerInterface $handler
     * @param MiddlewareInterface[]|null $middlewareStack
     */
    public function __construct(RequestHandlerInterface $handler, array $middlewareStack = null)
    {
        foreach ($middlewareStack as $middleware) {
            if (!$middleware instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException(
                    'All middlewares in the stack must implement the Psr\\Http\\Server\\MiddlewareInterface.'
                );
            }
        }

        $that = $this;

        $this->handler = $handler;
        $this->middlewareStack = is_array($middlewareStack) ? $middlewareStack : array();

        $this->server = new Server(function (ServerRequestInterface $request) use ($that) {
            $handler = new MiddlewareStackHandler($that->handler, $that->middlewareStack);

            return $handler->handle($request);
        });

        $this->server->on('error', function ($error) use ($that) {
            $that->emit('error', array($error));
        });
    }

    public function listen(ServerInterface $server) {
        $this->server->listen($server);
    }
}
