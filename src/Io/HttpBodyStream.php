<?php

namespace React\Http\Io;

use Evenement\EventEmitter;
use Psr\Http\Message\StreamInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

if (!(new \ReflectionParameter([StreamInterface::class, 'seek'], 0))->hasType()) {
    // load legacy class for psr/http-message v1.0 without native parameter types
    require __DIR__ . '/HttpBodyStream.v1.php';
} else {
    // use class with native types like `seek(int $offset, int $whence = SEEK_SET): void`

/**
 * [Internal] Bridge between StreamInterface from PSR-7 v2 and ReadableStreamInterface from ReactPHP
 *
 * This class is used in the server to stream the body of an incoming response
 * from the client. This allows us to stream big amounts of data without having
 * to buffer this data. Similarly, this used to stream the body of an outgoing
 * request body to the client. The data will be sent directly to the client.
 *
 * Note that this is an internal class only and nothing you should usually care
 * about. See the `StreamInterface` and `ReadableStreamInterface` for more
 * details.
 *
 * @see StreamInterface
 * @see ReadableStreamInterface
 * @internal
 * @see HttpBodyStream.v1.php (PSR-7 v1)
 */
class HttpBodyStream extends EventEmitter implements StreamInterface, ReadableStreamInterface
{
    public $input;
    private $closed = false;
    private $size;

    /**
     * @param ReadableStreamInterface $input Stream data from $stream as a body of a PSR-7 object4
     * @param int|null $size size of the data body
     */
    public function __construct(ReadableStreamInterface $input, $size)
    {
        $this->input = $input;
        $this->size = $size;

        $this->input->on('data', [$this, 'handleData']);
        $this->input->on('end', [$this, 'handleEnd']);
        $this->input->on('error', [$this, 'handleError']);
        $this->input->on('close', [$this, 'close']);
    }

    public function isReadable(): bool
    {
        return !$this->closed && $this->input->isReadable();
    }

    public function pause()
    {
        $this->input->pause();
    }

    public function resume()
    {
        $this->input->resume();
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

        $this->input->close();

        $this->emit('close');
        $this->removeAllListeners();
    }

    public function getSize(): ?int
    {
        return $this->size;
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
        return null;
    }

    /** @internal */
    public function handleData($data)
    {
        $this->emit('data', [$data]);
    }

    /** @internal */
    public function handleError(\Exception $e)
    {
        $this->emit('error', [$e]);
        $this->close();
    }

    /** @internal */
    public function handleEnd()
    {
        if (!$this->closed) {
            $this->emit('end');
            $this->close();
        }
    }
}
}
