<?php

namespace React\Http\Message;

/**
 * The `React\Http\Message\TimeoutException` is an `Exception` sub-class that will be used to reject
 * a request promise if the remote server does not respond in the configured time span
 */

final class TimeoutException extends \RuntimeException
{
    public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
