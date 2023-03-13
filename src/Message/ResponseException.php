<?php

namespace React\Http\Message;

use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Psr\Http\Message\ResponseInterface;

/**
 * The `React\Http\Message\ResponseException` is an `Exception` sub-class that will be used to reject
 * a request promise if the remote server returns a non-success status code
 * (anything but 2xx or 3xx).
 * You can control this behavior via the [`withRejectErrorResponse()` method](#withrejecterrorresponse).
 *
 * The `getCode(): int` method can be used to
 * return the HTTP response status code.
 */
final class ResponseException extends RuntimeException
{
    private $response;

    private $request;

    public function __construct(
        ResponseInterface $response,
        RequestInterface $request,
        $message = null,
        $code = null,
        $previous = null
    ) {
        if ($message === null) {
            $message = 'HTTP status code ' . $response->getStatusCode() . ' (' . $response->getReasonPhrase() . ')';
        }
        if ($code === null) {
            $code = $response->getStatusCode();
        }
        parent::__construct($message, $code, $previous);

        $this->response = $response;
        $this->request = $request;
    }

    /**
     * Access its underlying response object.
     *
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }
}
