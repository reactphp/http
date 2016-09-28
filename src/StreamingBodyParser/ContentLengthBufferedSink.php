<?php

namespace React\Http\StreamingBodyParser;

use React\Http\Request;
use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;

/**
 * Buffer the data coming in from a request until the specified length is reached.
 * Or until the promise is canceled.
 *
 * @internal
 */
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
     * @return ExtendedPromiseInterface
     */
    public static function createPromise(Request $request, $length)
    {
        return (new static($request, $length))->getDeferred()->promise();
    }

    /**
     * @param Request $request
     * @param $length
     */
    protected function __construct(Request $request, $length)
    {
        $this->deferred = new Deferred(function (callable $resolve) {
            $this->request->removeListener('data', [$this, 'feed']);

            $resolve($this->buffer);
        });
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

    /**
     * Check if we reached the expected length and when so resolve promise
     */
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

    /**
     * @return Deferred
     */
    protected function getDeferred()
    {
        return $this->deferred;
    }
}
