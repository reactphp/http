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
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;

        $this->request->on('data', [$this, 'feed']);
        $this->request->on('close', [$this, 'finish']);
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
        $this->request->removeListener('data', [$this, 'feed']);
        $this->request->removeListener('close', [$this, 'finish']);
        parse_str(trim($this->buffer), $result);
        foreach ($result as $key => $value) {
            $this->request->emit('post', [$key, $value]);
        }
    }
}
