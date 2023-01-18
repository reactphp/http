<?php

namespace React\Http\Psr7;

use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
class BufferStream implements StreamInterface
{
    /** @var int */
    private $hwm;

    /** @var string */
    private $buffer = '';

    /**
     * @param int $hwm High water mark, representing the preferred maximum
     *                 buffer size. If the size of the buffer exceeds the high
     *                 water mark, then calls to write will continue to succeed
     *                 but will return 0 to inform writers to slow down
     *                 until the buffer has been drained by reading from it.
     */
    public function __construct($hwm = 16384)
    {
        $this->hwm = $hwm;
    }

    public function __toString()
    {
        return $this->getContents();
    }

    public function getContents()
    {
        $buffer = $this->buffer;
        $this->buffer = '';

        return $buffer;
    }

    public function close()
    {
        $this->buffer = '';
    }

    public function detach()
    {
        $this->close();

        return null;
    }

    public function getSize()
    {
        return strlen($this->buffer);
    }

    public function isReadable()
    {
        return true;
    }

    public function isWritable()
    {
        return true;
    }

    public function isSeekable()
    {
        return false;
    }

    public function rewind()
    {
        $this->seek(0);
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        throw new \RuntimeException('Cannot seek a BufferStream');
    }

    public function eof()
    {
        return $this->buffer === '';
    }

    public function tell()
    {
        throw new \RuntimeException('Cannot determine the position of a BufferStream');
    }

    /**
     * Reads data from the buffer.
     */
    public function read($length)
    {
        $currentLength = strlen($this->buffer);

        if ($length >= $currentLength) {
            // No need to slice the buffer because we don't have enough data.
            $result = $this->buffer;
            $this->buffer = '';
        } else {
            // Slice up the result to provide a subset of the buffer.
            $result = substr($this->buffer, 0, $length);
            $this->buffer = substr($this->buffer, $length);
        }

        return $result;
    }

    /**
     * Writes data to the buffer.
     */
    public function write($string)
    {
        $this->buffer .= $string;

        if (strlen($this->buffer) >= $this->hwm) {
            return 0;
        }

        return strlen($string);
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed
     */
    public function getMetadata($key = null)
    {
        if ($key === 'hwm') {
            return $this->hwm;
        }

        return $key ? null : array();
    }
}
