<?php

namespace React\Http\Io;

use Evenement\EventEmitter;
use Psr\Http\Message\StreamInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

if (!(new \ReflectionParameter([StreamInterface::class, 'seek'], 0))->hasType()) {
    // load legacy class for psr/http-message v1.0 without native parameter types
    require __DIR__ . '/EmptyBodyStream.v1.php';
} else {
    // use class with native types like `seek(int $offset, int $whence = SEEK_SET): void`

/**
 * [Internal] Bridge between an empty StreamInterface from PSR-7 v2 and ReadableStreamInterface from ReactPHP
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
 * @see EmptyBodyStream.v1.php (PSR-7 v1)
 */
class EmptyBodyStream extends EventEmitter implements StreamInterface, ReadableStreamInterface
{
    private $closed = false;

    public function isReadable(): bool
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

    public function pipe(WritableStreamInterface $dest, array $options = [])
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        $this->emit('close');
        $this->removeAllListeners();
    }

    public function getSize(): ?int
    {
        return 0;
    }

    /** @ignore */
    public function __toString(): string
    {
        return '';
    }

    /** @ignore */
    public function detach()
    {
        return null;
    }

    /** @ignore */
    public function tell(): int
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function eof(): bool
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function isSeekable(): bool
    {
        return false;
    }

    /** @ignore */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function rewind(): void
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function isWritable(): bool
    {
        return false;
    }

    /** @ignore */
    public function write(string $string): int
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function read(int $length): string
    {
        throw new \BadMethodCallException();
    }

    /** @ignore */
    public function getContents(): string
    {
        return '';
    }

    /** @ignore */
    public function getMetadata(?string $key = null)
    {
        return ($key === null) ? [] : null;
    }
}
}
