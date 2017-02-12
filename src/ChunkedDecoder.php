<?php
namespace React\Http;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;
use Exception;

/** @internal */
class ChunkedDecoder extends EventEmitter implements ReadableStreamInterface
{
    const CRLF = "\r\n";

    private $closed = false;
    private $input;
    private $buffer = '';
    private $chunkSize = 0;
    private $actualChunksize = 0;

    public function __construct(ReadableStreamInterface $input)
    {
        $this->input = $input;

        $this->input->on('data', array($this, 'handleChunkHeader'));
        $this->input->on('end', array($this, 'handleEnd'));
        $this->input->on('error', array($this, 'handleError'));
        $this->input->on('close', array($this, 'close'));
    }

    public function isReadable()
    {
        return ! $this->closed && $this->input->isReadable();
    }

    public function pause()
    {
        $this->input->pause();
    }

    public function resume()
    {
        $this->input->resume();
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

        $this->buffer = '';

        $this->closed = true;

        $this->input->close();

        $this->emit('close');
        $this->removeAllListeners();
    }


    /** @internal */
    public function handleChunkHeader($data)
    {
        $this->buffer .= $data;

        $hexValue = strtok($this->buffer, static::CRLF);
        if (dechex(hexdec($hexValue)) != $hexValue) {
            $this->emit('error', array(new \Exception('Unable to identify ' . $hexValue . 'as hexadecimal number')));
            $this->close();
            return;
        }

        if (strpos($this->buffer, static::CRLF) !== false) {
            $this->chunkSize = hexdec($hexValue);

            $data = substr($this->buffer, strlen($hexValue) + 2);
            $this->buffer = '';
            // Chunk header is complete
            $this->input->removeListener('data', array($this, 'handleChunkHeader'));
            $this->input->on('data', array($this, 'handleChunkData'));
            if ($data !== '') {
                $this->input->emit('data', array($data));
            }
        }
    }

    /** @internal */
    public function handleChunkData($data)
    {
        $this->buffer .= $data;
        $chunk = substr($this->buffer, 0, $this->chunkSize);
        $this->actualChunksize = strlen($chunk);

        if ($this->chunkSize === $this->actualChunksize) {
            if ($this->chunkSize === 0 && strpos($this->buffer, static::CRLF) !== false) {
                $this->emit('end', array());
                $this->close();
                return;
            }

            if (strpos($this->buffer, static::CRLF) === false) {
                return;
            }

            $data = substr($this->buffer , $this->chunkSize + 2);
            $this->emit('data', array($chunk));

            $this->buffer = '';
            $this->chunkSize = 0;
            // chunk body is complete
            $this->input->removeListener('data', array($this, 'handleChunkData'));
            $this->input->on('data', array($this, 'handleChunkHeader'));
            if ($data !== '') {
                $this->input->emit('data', array($data));
            }
        }
    }

    /** @internal */
    public function handleEnd()
    {
        if (!$this->closed) {
            $this->buffer = '';
            $this->emit('end');
            $this->close();
        }
    }

    /** @internal */
    public function handleError(\Exception $e)
    {
        $this->emit('error', array($e));
        $this->close();
    }
}
