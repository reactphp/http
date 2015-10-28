<?php

namespace React\Http\Parser;

use React\Http\Request;
use React\Stream\ReadableStreamInterface;

class FormUrlencoded
{
    /**
     * @var string
     */
    protected $buffer = '';

    /**
     * @var ReadableStreamInterface
     */
    protected $stream;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @param ReadableStreamInterface $stream
     * @param Request $request
     */
    public function __construct(ReadableStreamInterface $stream, Request $request)
    {
        $this->stream = $stream;
        $this->request = $request;

        $this->stream->on('data', [$this, 'feed']);
        $this->stream->on('close', [$this, 'finish']);
    }

    /**
     * @param string $data
     */
    public function feed($data)
    {
        $this->buffer .= $data;

        if (
            array_key_exists('Content-Length', $this->request->getHeaders()) &&
            strlen($this->buffer) >= $this->request->getHeaders()['Content-Length']
        ) {
            $this->buffer = substr($this->buffer, 0, $this->request->getHeaders()['Content-Length']);
            $this->finish();
        }
    }

    public function finish()
    {
        $this->stream->removeListener('data', [$this, 'feed']);
        $this->stream->removeListener('close', [$this, 'finish']);
        parse_str(trim($this->buffer), $result);
        $this->request->setPost($result);
    }
}
