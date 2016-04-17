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


    public function _construct(Request $request)
    {
        $this->request = $request;
        $headers = $this->request->getHeaders();
        $headers = array_change_key_case($headers, CASE_LOWER);
        $this->boundary = preg_match('/boundary="?(.*)"?$/', $headers['content-type'], $matches)[0];

        $this->request->on('data', [$this, 'onData']);
    }

    public function onData($data)
    {

    }
}
