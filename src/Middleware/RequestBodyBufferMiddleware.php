<?php

namespace React\Http\Middleware;

use OverflowException;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Stream;
use React\Stream\ReadableStreamInterface;
use RingCentral\Psr7\BufferStream;

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

        // skip if body is already buffered
        if (!$body instanceof ReadableStreamInterface) {
            // replace with empty buffer if size limit is exceeded
            if ($body->getSize() > $this->sizeLimit) {
                $request = $request->withBody(new BufferStream(0));
            }

            return $stack($request);
        }

        // request body of known size exceeding limit
        $sizeLimit = $this->sizeLimit;
        if ($body->getSize() > $this->sizeLimit) {
            $sizeLimit = 0;
        }

        return Stream\buffer($body, $sizeLimit)->then(function ($buffer) use ($request, $stack) {
            $stream = new BufferStream(strlen($buffer));
            $stream->write($buffer);
            $request = $request->withBody($stream);

            return $stack($request);
        }, function ($error) use ($stack, $request, $body) {
            // On buffer overflow keep the request body stream in,
            // but ignore the contents and wait for the close event
            // before passing the request on to the next middleware.
            if ($error instanceof OverflowException) {
                return Stream\first($body, 'close')->then(function () use ($stack, $request) {
                    return $stack($request);
                });
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
