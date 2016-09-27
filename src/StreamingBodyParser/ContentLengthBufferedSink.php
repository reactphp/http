<?php

namespace React\Http\StreamingBodyParser;

use React\Http\Request;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class ContentLengthBufferedSink
{
    /**
     * @var Deferred
     */
    protected $deferred;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var string
     */
    protected $buffer = '';

    /**
     * @var int
     */
    protected $length;

    /**
     * @param Request $request
     * @param $length
     * @return PromiseInterface
     */
    public static function createPromise(Request $request, $length)
    {
        $deferred = new Deferred();
        new static($deferred, $request, $length);
        return $deferred->promise();
    }

    /**
     * @param Deferred $deferred
     * @param Request $request
     * @param $length
     */
    protected function __construct(Deferred $deferred, Request $request, $length)
    {
        $this->deferred = $deferred;
        $this->request = $request;
        $this->length = $length;
        $this->request->on('data', [$this, 'feed']);
        $this->check();
    }

    /**
     * @param string $data
     */
    public function feed($data)
    {
        $this->buffer .= $data;

        $this->check();
    }

    protected function check()
    {
        if (
            $this->length !== false &&
            strlen($this->buffer) >= $this->length
        ) {
            if (strlen($this->buffer) > $this->length) {
                $this->buffer = substr($this->buffer, 0, $this->length);
            }
            $this->request->removeListener('data', [$this, 'feed']);
            $this->deferred->resolve($this->buffer);
        }
    }
}
