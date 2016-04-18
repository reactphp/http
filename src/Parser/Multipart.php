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
    protected $ending = '';

    /**
     * @var int
     */
    protected $endingSize = 0;

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
            $this->setBoundary($matches[1]);
            $dataMethod = 'onData';
        }
        $this->request->on('data', [$this, $dataMethod]);
    }

    protected function setBoundary($boundary)
    {
        $this->boundary = $boundary;
        $this->ending = $this->boundary . "--\r\n";
        $this->endingSize = strlen($this->ending);
    }

    public function findBoundary($data)
    {
        $this->buffer .= $data;

        if (substr($this->buffer, 0, 3) === '---' && strpos($this->buffer, "\r\n") !== false) {
            $this->setBoundary(substr($this->buffer, 0, strpos($this->buffer, "\r\n")));
            $this->request->removeListener('data', [$this, 'findBoundary']);
            $this->request->on('data', [$this, 'onData']);
        }
    }

    public function onData($data)
    {
        $this->buffer .= $data;
    }
}
