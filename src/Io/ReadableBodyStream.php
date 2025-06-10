<?php

namespace React\Http\Io;

use Evenement\EventEmitter;
use Psr\Http\Message\StreamInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

/**
 * @internal
 */
class ReadableBodyStream extends EventEmitter implements ReadableStreamInterface, StreamInterface
{
    private $input;
    private $position = 0;
    private $size;
    private $closed = false;

    public function __construct(ReadableStreamInterface $input, $size = null)
    {
        $this->input = $input;
        $this->size = $size;

        $input->on('data', function ($data) use ($size) {
            $this->emit('data', [$data]);

            $this->position += \strlen($data);
            if ($size !== null && $this->position >= $size) {
                $this->handleEnd();
            }
        });
        $input->on('error', function ($error) {
            $this->emit('error', [$error]);
            $this->close();
        });
        $input->on('end', [$this, 'handleEnd']);
        $input->on('close', [$this, 'close']);
    }

    public function close(): void
    {
        if (!$this->closed) {
            $this->closed = true;
            $this->input->close();

            $this->emit('close');
            $this->removeAllListeners();
        }
    }

    public function isReadable(): bool
    {
        return $this->input->isReadable();
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

    public function eof(): bool
    {
        return !$this->isReadable();
    }

    public function __toString(): string
    {
        return '';
    }

    public function detach()
    {
        throw new \BadMethodCallException();
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function tell(): int
    {
        throw new \BadMethodCallException();
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new \BadMethodCallException();
    }

    public function rewind(): void
    {
        throw new \BadMethodCallException();
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new \BadMethodCallException();
    }

    public function read(int $length): string
    {
        throw new \BadMethodCallException();
    }

    public function getContents(): string
    {
        throw new \BadMethodCallException();
    }

    public function getMetadata($key = null)
    {
        return ($key === null) ? [] : null;
    }

    /** @internal */
    public function handleEnd()
    {
        if ($this->position !== $this->size && $this->size !== null) {
            $this->emit('error', [new \UnderflowException('Unexpected end of response body after ' . $this->position . '/' . $this->size . ' bytes')]);
        } else {
            $this->emit('end');
        }

        $this->close();
    }
}
