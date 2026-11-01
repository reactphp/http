<?php

namespace React\Http\Io;

use Evenement\EventEmitter;
use Psr\Http\Message\StreamInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

if (\PHP_VERSION_ID >= 70000 && \call_user_func(array(new \ReflectionMethod('\Psr\Http\Message\StreamInterface', 'eof'), 'hasReturnType'))) {
    // load class for psr/http-message v2 using native types like `eof(): bool`
    require __DIR__ . '/EmptyBodyStream.v2.php';
} else {
    // fall back to class without native types

/**
 * [Internal] Bridge between an empty StreamInterface from PSR-7 v1 and ReadableStreamInterface from ReactPHP
 *
 * This class is used in the server to represent an empty body stream of an
 * incoming response from the client. This is similar to the `HttpBodyStream`,
 * but is specifically designed for the common case of having an empty message
 * body.
 *
 * Note that this is an internal class only and nothing you should usually care
 * about. See the `StreamInterface` and `ReadableStreamInterface` for more
 * details.
 *
 * @see HttpBodyStream
 * @see StreamInterface
 * @see ReadableStreamInterface
 * @internal
 * @see EmptyBodyStream.v2.php (PSR-7 v2)
 */
class EmptyBodyStream extends EventEmitter implements StreamInterface, ReadableStreamInterface
{
    private $closed = false;

    public function isReadable()
    {
        return !$this->closed;
    }

    public function pause()
    {
        // NOOP
    }

    public function resume()
    {
        // NOOP
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        $this->emit('close');
        $this->removeAllListeners();
    }

    public function getSize()
    {
        return 0;
    }

    /** @ignore */
    public function __toString()
    {
        return '';
    }

    /** @ignore */
    public function detach()
    {
        return null;
    }

    /** @ignore */
    public function tell()
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function eof()
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function isSeekable()
    {
        return false;
    }

    /** @ignore */
    public function seek($offset, $whence = SEEK_SET)
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function rewind()
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function isWritable()
    {
        return false;
    }

    /** @ignore */
    public function write($string)
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function read($length)
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function getContents()
    {
        return '';
    }

    /** @ignore */
    public function getMetadata($key = null)
    {
        return ($key === null) ? array() : null;
    }
}
}
