<?php

namespace React\Http\Message;

use React\Http\Io\HttpBodyStream;
use React\Stream\ReadableStreamInterface;
use RingCentral\Psr7\Response as Psr7Response;
use Psr\Http\Message\StreamInterface;

/**
 * Represents an outgoing server response message.
 *
 * ```php
 * $response = new React\Http\Message\Response(
 *     200,
 *     array(
 *         'Content-Type' => 'text/html'
 *     ),
 *     "<html>Hello world!</html>\n"
 * );
 * ```
 *
 * This class implements the
 * [PSR-7 `ResponseInterface`](https://www.php-fig.org/psr/psr-7/#33-psrhttpmessageresponseinterface)
 * which in turn extends the
 * [PSR-7 `MessageInterface`](https://www.php-fig.org/psr/psr-7/#31-psrhttpmessagemessageinterface).
 *
 * > Internally, this class extends the underlying `\RingCentral\Psr7\Response`
 *   class. The only difference is that this class will accept implemenations
 *   of ReactPHPs `ReadableStreamInterface` for the `$body` argument. This base
 *   class is considered an implementation detail that may change in the future.
 *
 * @see \Psr\Http\Message\ResponseInterface
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
}
