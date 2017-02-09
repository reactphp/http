<?php
namespace React\Http;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;
use Exception;

class ChunkedDecoder extends EventEmitter implements ReadableStreamInterface
{
    const CRLF = "\r\n";

    private $closed = false;
    private $input;
    private $buffer = '';
    private $chunkSize = 0;
    private $actualChunksize = 0;
    private $chunkHeaderComplete = false;

    public function __construct(ReadableStreamInterface $input)
    {
        $this->input = $input;

        $this->input->on('data', array($this, 'handleData'));
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

        $this->closed = true;

        $this->input->close();

        $this->emit('close');
        $this->removeAllListeners();
    }

    /**
     * Extracts the hexadecimal header and removes it from the given data string
     *
     * @param string $data - complete or incomplete chunked string
     * @return string
     */
    private function handleChunkHeader($data)
    {
        $hexValue = strtok($this->buffer . $data, static::CRLF);
        if ($this->isLineComplete($this->buffer . $data, $hexValue, strlen($hexValue))) {

            if (dechex(hexdec($hexValue)) != $hexValue) {
                $this->emit('error', array(new \Exception('Unable to identify ' . $hexValue . 'as hexadecimal number')));
                $this->close();
                return;
            }

            $this->chunkSize = hexdec($hexValue);
            $this->chunkHeaderComplete = true;

            $data = substr($this->buffer . $data, strlen($hexValue) + 2);
            $this->buffer = '';
            // Chunk header is complete
            return $data;
        }

        $this->buffer .= $data;
        $data = '';
        // Chunk header isn't complete, buffer
        return $data;
    }

    /**
     * Extracts the chunk data and removes it from the income data string
     *
     * @param unknown $data - string without the hexadecimal header
     * @return string
     */
    private function handleChunkData($data)
    {
        $chunk = substr($this->buffer . $data, 0, $this->chunkSize);
        $this->actualChunksize = strlen($chunk);

        if ($this->chunkSize == $this->actualChunksize) {
            $data = $this->sendChunk($data, $chunk);
        } elseif ($this->actualChunksize < $this->chunkSize) {
            $this->buffer .= $data;
            $data = '';
        }

        return $data;
    }

    /**
     * Sends the chunk or ends the stream
     *
     * @param string $data - incomed data stream the chunk will be removed from this string
     * @param string $chunk - chunk which will be emitted
     * @return string - rest data string
     */
    private function sendChunk($data, $chunk)
    {
        if ($this->chunkSize == 0 && $this->isLineComplete($this->buffer . $data, $chunk, $this->chunkSize)) {
            $this->emit('end', array());
            return;
        }

        if (!$this->isLineComplete($this->buffer . $data, $chunk, $this->chunkSize)) {
            $this->emit('error', array(new \Exception('Chunk doesn\'t end with new line delimiter')));
            $this->close();
            return;
        }

        $data = substr($this->buffer . $data, $this->chunkSize + 2);
        $this->emit('data', array($chunk));

        $this->buffer = '';
        $this->chunkSize = 0;
        $this->chunkHeaderComplete = false;

        return $data;
    }

    /**
     * Checks if the given chunk is ending with a "\r\n" at the start of the data string
     *
     * @param string $data - complete data string
     * @param string $chunk - string which should end with "\r\n"
     * @param unknown $length - possible length of the data chunk
     * @return boolean
     */
    private function isLineComplete($data, $chunk, $length)
    {
        if (substr($data, 0, $length + 2) == $chunk . static::CRLF) {
            return true;
        }
        return false;
    }

    /** @internal */
    public function handleEnd()
    {
        if (! $this->closed) {
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

    /** @internal */
    public function handleData($data)
    {
        while (strlen($data) != 0) {
            if (! $this->chunkHeaderComplete) {
                $data = $this->handleChunkHeader($data);
            }
            // Not 'else', chunkHeaderComplete can change in 'handleChunkHeader'
            if ($this->chunkHeaderComplete) {
                $data = $this->handleChunkData($data);
            }
        }
    }
}
