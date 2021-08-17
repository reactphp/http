<?php

namespace React\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\HttpBodyStream;
use React\Http\Io\PauseBufferStream;
use React\Promise;
use React\Promise\PromiseInterface;
use React\Promise\Deferred;
use React\Stream\ReadableStreamInterface;

/**
 * Closes any inactive connection after the specified amount of seconds since last activity.
 *
 * This allows you to set an alternative timeout to the default one minute (60 seconds). For example
 * thirteen and a half seconds:
 *
 * ```php
 * $http = new React\Http\HttpServer(
 *     new React\Http\Middleware\InactiveConnectionTimeoutMiddleware(13.5),
 *     $handler
 * );
 *
 * > Internally, this class is used as a "value object" to override the default timeout of one minute.
 *   As such it doesn't have any behavior internally, that is all in the internal "StreamingServer".
 */
final class InactiveConnectionTimeoutMiddleware
{
    const DEFAULT_TIMEOUT = 60;

    /**
     * @var float
     */
    private $timeout;

    /**
     * @param float $timeout
     */
    public function __construct($timeout = self::DEFAULT_TIMEOUT)
    {
        $this->timeout = $timeout;
    }

    public function __invoke(ServerRequestInterface $request, $next)
    {
        return $next($request);
    }

    /**
     * @return float
     */
    public function getTimeout()
    {
        return $this->timeout;
    }
}
