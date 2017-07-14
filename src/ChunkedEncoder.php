<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

/** @internal */
class ChunkedEncoder extends EventEmitter implements ReadableStreamInterface
{
    private $input;
    private $closed;

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

        $this->closed = true;
        $this->input->close();

        $this->emit('close');
        $this->removeAllListeners();
    }

    /** @internal */
    public function handleData($data)
    {
        if ($data === '') {
            return;
        }

        $completeChunk = $this->createChunk($data);

        $this->emit('data', array($completeChunk));
    }

    /** @internal */
    public function handleError(\Exception $e)
    {
        $this->emit('error', array($e));
        $this->close();
    }

    /** @internal */
    public function handleEnd()
    {
        $this->emit('data', array("0\r\n\r\n"));

        if (!$this->closed) {
            $this->emit('end');
            $this->close();
        }
    }

    /**
     * @param string $data - string to be transformed in an valid
     *                       HTTP encoded chunk string
     * @return string
     */
    private function createChunk($data)
    {
        $byteSize = strlen($data);
        $byteSize = dechex($byteSize);
        $chunkBeginning = $byteSize . "\r\n";

        return $chunkBeginning . $data . "\r\n";
    }

}
