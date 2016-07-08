<?php

namespace React\Http\StreamingBodyParser;

use Evenement\EventEmitterTrait;
use React\Http\Request;

class RawBody implements StreamingParserInterface
{
    use EventEmitterTrait;

    protected $buffer = '';
    protected $contentLength;

    public function __construct(Request $request)
    {
        $headers = $request->getHeaders();
        $headers = array_change_key_case($headers, CASE_LOWER);

        $this->contentLength = $headers['content-length'];
        $request->on('data', [$this, 'feed']);
    }

    public function feed($data)
    {
        $this->buffer .= $data;

        if (strlen($this->buffer) >= $this->contentLength) {
            $this->emit('body', [$this->buffer]);
            $this->emit('end');
        }
    }
}
