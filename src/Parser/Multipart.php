<?php

namespace React\Http\Parser;

use Evenement\EventEmitterTrait;
use React\Http\Request;

class Multipart implements ParserInterface
{
    use EventEmitterTrait;

    /**
     * @var string
     */
    protected $buffer = '';

    /**
     * @var string
     */
    protected $boundary;

    /**
     * @var Request
     */
    protected $request;


    public function __construct(Request $request)
    {
        $this->request = $request;
        $headers = $this->request->getHeaders();
        $headers = array_change_key_case($headers, CASE_LOWER);
        preg_match('/boundary="?(.*)"?$/', $headers['content-type'], $matches);

        $dataMethod = 'findBoundary';
        if (isset($matches[1])) {
            $this->boundary = $matches[1];
            $dataMethod = 'onData';
        }
        $this->request->on('data', [$this, $dataMethod]);
    }

    public function findBoundary($data)
    {
        $this->buffer .= $data;

        if (substr($this->buffer, 0, 3) === '---' && strpos($this->buffer, "\r\n") !== false) {
            $this->boundary = substr($this->buffer, 0, strpos($this->buffer, "\r\n"));
            $this->request->removeListener('data', [$this, 'findBoundary']);
            $this->request->on('data', [$this, 'onData']);
        }
    }

    public function onData($data)
    {
        $this->buffer .= $data;
    }
}
