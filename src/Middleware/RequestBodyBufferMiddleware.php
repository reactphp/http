<?php

namespace React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use React\Promise\Stream;
use React\Stream\ReadableStreamInterface;
use RingCentral\Psr7\BufferStream;
use OverflowException;

final class RequestBodyBufferMiddleware
{
    private $sizeLimit;

    /**
     * @param int|null $sizeLimit Either an int with the max request body size
     *                            or null to use post_max_size from PHP's
     *                            configuration. (Note that the value from
     *                            the CLI configuration will be used.)
     */
    public function __construct($sizeLimit = null)
    {
        if ($sizeLimit === null) {
            $sizeLimit = $this->iniMaxPostSize();
        }

        $this->sizeLimit = $sizeLimit;
    }

    public function __invoke(ServerRequestInterface $request, $stack)
    {
        $body = $request->getBody();

        // request body of known size exceeding limit
        if ($body->getSize() > $this->sizeLimit) {
            return new Response(413, array('Content-Type' => 'text/plain'), 'Request body exceeds allowed limit');
        }

        if (!$body instanceof ReadableStreamInterface) {
            return $stack($request);
        }

        return Stream\buffer($body, $this->sizeLimit)->then(function ($buffer) use ($request, $stack) {
            $stream = new BufferStream(strlen($buffer));
            $stream->write($buffer);
            $request = $request->withBody($stream);

            return $stack($request);
        }, function($error) {
            // request body of unknown size exceeding limit during buffering
            if ($error instanceof OverflowException) {
                return new Response(413, array('Content-Type' => 'text/plain'), 'Request body exceeds allowed limit');
            }

            throw $error;
        });
    }

    /**
     * Gets post_max_size from PHP's configuration expressed in bytes
     *
     * @return int
     * @link http://php.net/manual/en/ini.core.php#ini.post-max-size
     * @codeCoverageIgnore
     */
    private function iniMaxPostSize()
    {
        $size = ini_get('post_max_size');
        $suffix = strtoupper(substr($size, -1));
        if ($suffix === 'K') {
            return substr($size, 0, -1) * 1024;
        }
        if ($suffix === 'M') {
            return substr($size, 0, -1) * 1024 * 1024;
        }
        if ($suffix === 'G') {
            return substr($size, 0, -1) * 1024  * 1024 * 1024;
        }

        return $size;
    }
}
