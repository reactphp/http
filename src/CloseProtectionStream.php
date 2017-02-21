<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

/** @internal
 * This stream is used to protect the passed stream against closing.
 * */
class CloseProtectionStream extends EventEmitter implements ReadableStreamInterface
{
    private $connection;
    private $closed = false;

    /**
     * @param ReadableStreamInterface $input stream that will be paused instead of closed on an 'close' event.
     */
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
        if ($this->closed) {
            return;
        }

        $this->input->pause();
    }

    public function resume()
    {
        if ($this->closed) {
            return;
        }

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

         $this->emit('close');

         // 'pause' the stream avoids additional traffic transferred by this stream
         $this->input->pause();

         $this->input->removeListener('data', array($this, 'handleData'));
         $this->input->removeListener('error', array($this, 'handleError'));
         $this->input->removeListener('end', array($this, 'handleEnd'));
         $this->input->removeListener('close', array($this, 'close'));

         $this->removeAllListeners();
     }

     /** @internal */
     public function handleData($data)
     {
        $this->emit('data', array($data));
     }

     /** @internal */
     public function handleEnd()
     {
         $this->emit('end');
         $this->close();
     }

     /** @internal */
     public function handleError(\Exception $e)
     {
         $this->emit('error', array($e));
     }

}
