<?php

namespace React\Http\Client;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

/**
 * @event data ($bodyChunk)
 * @event error
 * @event end
 * @internal
 */
class Response extends EventEmitter implements ReadableStreamInterface
{
    private $stream;
    private $protocol;
    private $version;
    private $code;
    private $reasonPhrase;
    private $headers;
    private $readable = true;

    public function __construct(ReadableStreamInterface $stream, $protocol, $version, $code, $reasonPhrase, $headers)
    {
        $this->stream = $stream;
        $this->protocol = $protocol;
        $this->version = $version;
        $this->code = $code;
        $this->reasonPhrase = $reasonPhrase;
        $this->headers = $headers;

        if (strtolower($this->getHeaderLine('Transfer-Encoding')) === 'chunked') {
            $this->stream = new ChunkedStreamDecoder($stream);
            $this->removeHeader('Transfer-Encoding');
        }

        $this->stream->on('data', array($this, 'handleData'));
        $this->stream->on('error', array($this, 'handleError'));
        $this->stream->on('end', array($this, 'handleEnd'));
        $this->stream->on('close', array($this, 'handleClose'));
    }

    public function getProtocol()
    {
        return $this->protocol;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getReasonPhrase()
    {
        return $this->reasonPhrase;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    private function removeHeader($name)
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($name, $key) === 0) {
                unset($this->headers[$key]);
                break;
            }
        }
    }

    private function getHeader($name)
    {
        $name = strtolower($name);
        $normalized = array_change_key_case($this->headers, CASE_LOWER);

        return isset($normalized[$name]) ? (array)$normalized[$name] : array();
    }

    private function getHeaderLine($name)
    {
        return implode(', ' , $this->getHeader($name));
    }

    /** @internal */
    public function handleData($data)
    {
        if ($this->readable) {
            $this->emit('data', array($data));
        }
    }

    /** @internal */
    public function handleEnd()
    {
        if (!$this->readable) {
            return;
        }
        $this->emit('end');
        $this->close();
    }

    /** @internal */
    public function handleError(\Exception $error)
    {
        if (!$this->readable) {
            return;
        }
        $this->emit('error', array(new \RuntimeException(
            "An error occurred in the underlying stream",
            0,
            $error
        )));

        $this->close();
    }

    /** @internal */
    public function handleClose()
    {
        $this->close();
    }

    public function close()
    {
        if (!$this->readable) {
            return;
        }

        $this->readable = false;
        $this->stream->close();

        $this->emit('close');
        $this->removeAllListeners();
    }

    public function isReadable()
    {
        return $this->readable;
    }

    public function pause()
    {
        if (!$this->readable) {
            return;
        }

        $this->stream->pause();
    }

    public function resume()
    {
        if (!$this->readable) {
            return;
        }

        $this->stream->resume();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }
}
