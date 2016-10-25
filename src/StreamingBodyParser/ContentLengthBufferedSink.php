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
    private $deferred;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var string
     */
    private $buffer = '';

    /**
     * @var int
     */
    private $length;

    /**
     * @param Request $request
     * @param int $length
     * @return ExtendedPromiseInterface
     */
    public static function createPromise(Request $request, $length)
    {
        return (new static($request, $length))->getDeferred()->promise();
    }

    /**
     * @param Request $request
     * @param int $length
     */
    protected function __construct(Request $request, $length)
    {
        $this->deferred = new Deferred(function (callable $resolve) {
            $this->request->removeListener('data', [$this, 'feed']);
            $this->request->removeListener('end', [$this, 'finish']);

            $resolve($this->buffer);
        });
        $this->request = $request;
        $this->length = (int)$length;
        $this->request->on('data', [$this, 'feed']);
        $this->request->on('end', [$this, 'finish']);
        $this->check();
    }

    /**
     * @param string $data
     *
     * @internal
     */
    public function feed($data)
    {
        $this->buffer .= $data;

        $this->check();
    }

    /**
     * @internal
     */
    public function finish()
    {
        $this->resolve();
    }

    /**
     * Check if we reached the expected length and when so resolve promise
     */
    protected function check()
    {
        if (strlen($this->buffer) >= $this->length) {
            $this->resolve();
        }
    }

    protected function resolve()
    {
        if (strlen($this->buffer) > $this->length) {
            $this->buffer = substr($this->buffer, 0, $this->length);
        }
        $this->request->removeListener('data', [$this, 'feed']);
        $this->request->removeListener('end', [$this, 'finish']);
        $this->deferred->resolve($this->buffer);
    }

    /**
     * @return Deferred
     */
    protected function getDeferred()
    {
        return $this->deferred;
    }
}
