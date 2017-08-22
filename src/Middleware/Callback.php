<?php

namespace React\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use React\Http\MiddlewareInterface;
use React\Http\MiddlewareStackInterface;
use React\Promise;
use Psr\Http\Message\ServerRequestInterface;

final class Callback implements MiddlewareInterface
{
    private $callback;

    public function __construct($callback)
    {
        $this->callback = $callback;
    }

    public function process(ServerRequestInterface $request, MiddlewareStackInterface $stack)
    {
        $callback = $this->callback;

        $cancel = null;
        return new Promise\Promise(function ($resolve, $reject) use ($callback, $request, &$cancel, $stack) {
            $cancel = Promise\resolve($callback($request));
            $cancel->then(function ($response) use ($stack) {
                if ($response instanceof ResponseInterface) {
                    return $response;
                }

                // Assuming since $response isn't a response it is a request
                return $stack->process($response);
            })->done($resolve, $reject);
        }, function () use (&$cancel) {
            if ($cancel instanceof Promise\CancellablePromiseInterface) {
                $cancel->cancel();
            }
        });
    }
}
