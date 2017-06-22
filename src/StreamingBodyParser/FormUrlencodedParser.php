<?php

namespace React\Http\StreamingBodyParser;

use Evenement\EventEmitter;
use Psr\Http\Message\RequestInterface;
use React\Http\HttpBodyStream;

final class FormUrlencodedParser extends EventEmitter
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var HttpBodyStream
     */
    private $body;

    /**
     * @var string
     */
    private $buffer = '';

    /**
     * @param RequestInterface $request
     */
    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
        $this->body = $this->request->getBody();
        $this->body->on('data', array($this, 'onData'));
        $this->body->on('end', array($this, 'onEnd'));
    }

    /**
     * @internal
     */
    public function onData($data)
    {
        $this->buffer .= $data;

        $pos = strrpos($this->buffer, '&');
        if ($pos === false) {
            return;
        }

        $buffer = substr($this->buffer, 0, $pos);
        $this->buffer = substr($this->buffer, $pos + 1);

        $this->parse($buffer);
    }

    /**
     * @internal
     */
    public function onEnd()
    {
        $this->body->removeAllListeners();
        $this->parse($this->buffer);
        $this->emit('end');
    }

    private function parse($buffer)
    {
        foreach (explode('&', $buffer) as $chunk) {
            $kv = explode('=', $chunk);
            $kv[0] = rawurldecode($kv[0]);
            if (!isset($kv[1])) {
                $kv[1] = null;
            } else {
                $kv[1] = rawurldecode($kv[1]);
            }
            $this->emit(
                'post',
                $kv
            );
        }
    }
}