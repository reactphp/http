<?php

namespace React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\MiddlewareInterface;
use React\Http\MiddlewareStackInterface;
use React\Http\Response;
use React\Promise\Stream;
use React\Stream\ReadableStreamInterface;
use RingCentral\Psr7\BufferStream;

final class Buffer implements MiddlewareInterface
{
    private $sizeLimit;

    public function __construct($sizeLimit = null)
    {
        if ($sizeLimit === null) {
            $sizeLimit = $this->iniMaxPostSize();
        }

        $this->sizeLimit = $sizeLimit;
    }

    public function process(ServerRequestInterface $request, MiddlewareStackInterface $stack)
    {
        $size = $request->getBody()->getSize();

        if ($size === null) {
            return new Response(411, array('Content-Type' => 'text/plain'), 'No Content-Length header given');
        }

        if ($size > $this->sizeLimit) {
            return new Response(413, array('Content-Type' => 'text/plain'), 'Request body exceeds allowed limit');
        }

        $body = $request->getBody();
        if (!$body instanceof ReadableStreamInterface) {
            return $stack->process($request);
        }

        return Stream\buffer($body)->then(function ($buffer) use ($request, $stack) {
            $stream = new BufferStream(strlen($buffer));
            $stream->write($buffer);
            $request = $request->withBody($stream);

            return $stack->process($request);
        });
    }

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
