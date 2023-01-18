<?php

namespace React\Http\Psr7;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Returns the string representation of an HTTP message.
 *
 * @param MessageInterface $message Message to convert to a string.
 */
function str(MessageInterface $message)
{
    if ($message instanceof RequestInterface) {
        $msg = trim($message->getMethod() . ' '
                . $message->getRequestTarget())
            . ' HTTP/' . $message->getProtocolVersion();
        if (!$message->hasHeader('host')) {
            $msg .= "\r\nHost: " . $message->getUri()->getHost();
        }
    } elseif ($message instanceof ResponseInterface) {
        $msg = 'HTTP/' . $message->getProtocolVersion() . ' '
            . $message->getStatusCode() . ' '
            . $message->getReasonPhrase();
    } else {
        throw new \InvalidArgumentException('Unknown message type');
    }

    foreach ($message->getHeaders() as $name => $values) {
        if (strtolower($name) === 'set-cookie') {
            foreach ($values as $value) {
                $msg .= "\r\n{$name}: " . $value;
            }
        } else {
            $msg .= "\r\n{$name}: " . implode(', ', $values);
        }
    }

    return "{$msg}\r\n\r\n" . $message->getBody();
}
