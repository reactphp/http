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
    const MAX_CHUNK_HEADER_SIZE = 1024;

    private $closed = false;
    private $input;
    private $buffer = '';
    private $chunkSize = 0;
    private $transferredSize = 0;
    private $headerCompleted = false;

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
    public function handleEnd()
    {
        if (!$this->closed) {
            $this->handleError(new \Exception('Unexpected end event'));
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
        $this->buffer .= $data;

        while ($this->buffer !== '') {
            if (!$this->headerCompleted) {
                $positionCrlf = strpos($this->buffer, static::CRLF);

                if ($positionCrlf === false) {
                    // Header shouldn't be bigger than 1024 bytes
                    if (isset($this->buffer[static::MAX_CHUNK_HEADER_SIZE])) {
                        $this->handleError(new \Exception('Chunk header size inclusive extension bigger than' . static::MAX_CHUNK_HEADER_SIZE. ' bytes', 400));
                    }
                    return;
                }

                $header = strtolower((string)substr($this->buffer, 0, $positionCrlf));
                $hexValue = $header;

                if (strpos($header, ';') !== false) {
                    $array = explode(';', $header);
                    $hexValue = $array[0];
                }

                if ($hexValue !== '') {
                    $hexValue = ltrim($hexValue, "0");
                    if ($hexValue === '') {
                        $hexValue = "0";
                    }
                }

                $this->chunkSize = hexdec($hexValue);
                if (dechex($this->chunkSize) !== $hexValue) {
                    $this->handleError(new \Exception($hexValue . ' is not a valid hexadecimal number', 400));
                    return;
                }

                $this->buffer = (string)substr($this->buffer, $positionCrlf + 2);
                $this->headerCompleted = true;
                if ($this->buffer === '') {
                    return;
                }
            }

            $chunk = (string)substr($this->buffer, 0, $this->chunkSize - $this->transferredSize);

            if ($chunk !== '') {
                $this->transferredSize += strlen($chunk);
                $this->emit('data', array($chunk));
                $this->buffer = (string)substr($this->buffer, strlen($chunk));
            }

            $positionCrlf = strpos($this->buffer, static::CRLF);

            if ($positionCrlf === 0) {
                if ($this->chunkSize === 0) {
                    $this->emit('end');
                    $this->close();
                    return;
                }
                $this->chunkSize = 0;
                $this->headerCompleted = false;
                $this->transferredSize = 0;
                $this->buffer = (string)substr($this->buffer, 2);
            }

            if ($positionCrlf !== 0 && $this->chunkSize === $this->transferredSize && strlen($this->buffer) > 2) {
                // the first 2 characters are not CLRF, send error event
                $this->handleError(new \Exception('Chunk does not end with a CLRF', 400));
                return;
            }

            if ($positionCrlf !== 0 && strlen($this->buffer) < 2) {
                // No CLRF found, wait for additional data which could be a CLRF
                return;
            }
        }
    }
}
