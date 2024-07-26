<?php

namespace React\Http\Io;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

/**
 * [Internal] Pauses a given stream and buffers all events while paused
 *
 * This class is used to buffer all events that happen on a given stream while
 * it is paused. This allows you to pause a stream and no longer watch for any
 * of its events. Once the stream is resumed, all buffered events will be
 * emitted. Explicitly closing the resulting stream clears all buffers.
 *
 * Note that this is an internal class only and nothing you should usually care
 * about.
 *
 * @see ReadableStreamInterface
 * @internal
 */
class PauseBufferStream extends EventEmitter implements ReadableStreamInterface
{
    /**
     * @var ReadableStreamInterface
     */
    private $input;

    /**
     * @var bool
     */
    private $closed = false;

    /**
     * @var bool
     */
    private $paused = false;

    /**
     * @var string
     */
    private $dataPaused = '';

    /**
     * @var bool
     */
    private $endPaused = false;

    /**
     * @var bool
     */
    private $closePaused = false;

    /**
     * @var bool
     */
    private $errorPaused = false;

    /**
     * @var bool
     */
    private $implicit = false;

    public function __construct(ReadableStreamInterface $input)
    {
        $this->input = $input;

        $this->input->on('data', [$this, 'handleData']);
        $this->input->on('end', [$this, 'handleEnd']);
        $this->input->on('error', [$this, 'handleError']);
        $this->input->on('close', [$this, 'handleClose']);
    }

    /**
     * pause and remember this was not explicitly from user control
     *
     * @internal
     */
    public function pauseImplicit()
    {
        $this->pause();
        $this->implicit = true;
    }

    /**
     * resume only if this was previously paused implicitly and not explicitly from user control
     *
     * @internal
     */
    public function resumeImplicit()
    {
        if ($this->implicit) {
            $this->resume();
        }
    }

    /**
     * @return bool
     */
    public function isReadable(): bool
    {
        return !$this->closed;
    }

    public function pause(): void
    {
        if ($this->closed) {
            return;
        }

        $this->input->pause();
        $this->paused = true;
        $this->implicit = false;
    }

    public function resume(): void
    {
        if ($this->closed) {
            return;
        }

        $this->paused = false;
        $this->implicit = false;

        if ($this->dataPaused !== '') {
            $this->emit('data', [$this->dataPaused]);
            $this->dataPaused = '';
        }

        if ($this->errorPaused) {
            $this->emit('error', [$this->errorPaused]);
            $this->close();
            return;
        }

        if ($this->endPaused) {
            $this->endPaused = false;
            $this->emit('end');
            $this->close();
            return;
        }

        if ($this->closePaused) {
            $this->closePaused = false;
            $this->close();
            return;
        }

        $this->input->resume();
    }

    public function pipe(WritableStreamInterface $dest, array $options = []): WritableStreamInterface
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    /**
     * @return void
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->dataPaused = '';
        $this->endPaused = $this->closePaused = false;
        $this->errorPaused = null;

        $this->input->close();

        $this->emit('close');
        $this->removeAllListeners();
    }

    /** @internal */
    public function handleData($data): void
    {
        if ($this->paused) {
            $this->dataPaused .= $data;
            return;
        }

        $this->emit('data', [$data]);
    }

    /** @internal */
    public function handleError(\Exception $e): void
    {
        if ($this->paused) {
            $this->errorPaused = $e;
            return;
        }

        $this->emit('error', [$e]);
        $this->close();
    }

    /** @internal */
    public function handleEnd(): void
    {
        if ($this->paused) {
            $this->endPaused = true;
            return;
        }

        if (!$this->closed) {
            $this->emit('end');
            $this->close();
        }
    }

    /** @internal */
    public function handleClose(): void
    {
        if ($this->paused) {
            $this->closePaused = true;
            return;
        }

        $this->close();
    }
}
