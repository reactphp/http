<?php

namespace React\Http\Parser;

use Evenement\EventEmitterTrait;
use React\Http\Request;

class FormUrlencoded implements ParserInterface
{
    use EventEmitterTrait;

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
        $this->request->on('end', [$this, 'finish']);

        $headers = $this->request->getHeaders();
        $headers = array_change_key_case($headers, CASE_LOWER);

        if (isset($headers['content-length'])) {
            $this->contentLength = $headers['content-length'];
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
            $this->emit('post', [$key, $value]);
        }
        $this->emit('end');
    }
}
