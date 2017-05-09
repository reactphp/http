<?php

namespace React\Http;

use RingCentral\Psr7\Response as Psr7Response;
use React\Stream\ReadableStreamInterface;
use React\Http\HttpBodyStream;

/**
 * Implementation of the PSR-7 ResponseInterface
 * This class is an extension of RingCentral\Psr7\Response.
 * The only difference is that this class will accept implemenations
 * of the ReactPHPs ReadableStreamInterface for $body.
 */
class Response extends Psr7Response
{
    public function __construct(
        $status = 200,
        array $headers = array(),
        $body = null,
        $version = '1.1',
        $reason = null
    ) {
        if ($body instanceof ReadableStreamInterface) {
            $body = new HttpBodyStream($body, null);
        }

        parent::__construct(
            $status,
            $headers,
            $body,
            $version,
            $reason
        );
    }

    /**
     * Get connected client
     *
     * @return ConnectionInterface
     */
    public function getConnection()
    {
        return $this->conn;
    }

}
