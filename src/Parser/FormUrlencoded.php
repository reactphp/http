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
     * @var Request
     */
    protected $request;

    /**
     * @var bool|integer
     */
    protected $contentLength = false;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;

        $this->request->on('data', [$this, 'feed']);
        $this->request->on('close', [$this, 'finish']);

        if (isset($this->request->getHeaders()['Content-Length'])) {
            $this->contentLength = $this->request->getHeaders()['Content-Length'];
        }
    }

    /**
     * @param string $data
     */
    public function feed($data)
    {
        $this->buffer .= $data;

        if (
            $this->contentLength !== false &&
            strlen($this->buffer) >= $this->contentLength
        ) {
            $this->buffer = substr($this->buffer, 0, $this->contentLength);
            $this->finish();
        }
    }

    public function finish()
    {
        $this->request->removeListener('data', [$this, 'feed']);
        $this->request->removeListener('close', [$this, 'finish']);
        parse_str(trim($this->buffer), $result);
        foreach ($result as $key => $value) {
            $this->request->emit('post', [$key, $value]);
        }
    }
}
