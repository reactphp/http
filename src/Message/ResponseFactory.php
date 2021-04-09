<?php

namespace React\Http\Message;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use React\Stream\ReadableStreamInterface;

final class ResponseFactory
{
    /**
     * @param string|ReadableStreamInterface|StreamInterface $body
     * @return ResponseInterface
     */
    public static function html($body)
    {
        return new Response(
            200,
            array(
                'Content-Type' => 'text/html; charset=utf-8',
            ),
            $body
        );
    }

    /**
     * @param mixed $body
     * @return ResponseInterface
     */
    public static function json($body)
    {
        $json = @\json_encode($body);

        if (\json_last_error() !== JSON_ERROR_NONE || ($json === null && $body !== null)) {
            if (\function_exists('json_last_error_msg')) {
                throw new \InvalidArgumentException('Error encoding JSON: ' . \json_last_error_msg());
            }

            throw new \InvalidArgumentException('Error encoding JSON');
        }

        return new Response(
            200,
            array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
            $json
        );
    }

    /**
     * @param string|ReadableStreamInterface|StreamInterface $body
     * @return ResponseInterface
     */
    public static function plain($body)
    {
        return new Response(
            200,
            array(
                'Content-Type' => 'text/plain; charset=utf-8',
            ),
            $body
        );
    }

    /**
     * @param string|ReadableStreamInterface|StreamInterface $body
     * @return ResponseInterface
     */
    public static function xml($body)
    {
        return new Response(
            200,
            array(
                'Content-Type' => 'application/xml; charset=utf-8',
            ),
            $body
        );
    }
}
