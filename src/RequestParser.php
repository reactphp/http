<?php

namespace React\Http;

use Evenement\EventEmitter;
use GuzzleHttp\Psr7 as gPsr;
use React\Stream\ReadableStreamInterface;

/**
 * @event headers
 * @event error
 */
class RequestParser extends EventEmitter
{
    private $stream;

    private $buffer = '';
    private $maxSize = 4096;

    /**
     * @var Request
     */
    private $request;

    public function __construct(ReadableStreamInterface $conn)
    {
        $this->stream = $conn;

        $this->stream->on('data', [$this, 'feed']);
    }

    public function feed($data)
    {
        $this->buffer .= $data;

        if (!$this->request && false !== strpos($this->buffer, "\r\n\r\n")) {

            // Extract the header from the buffer
            // in case the content isn't complete
            list($headers, $buffer) = explode("\r\n\r\n", $this->buffer, 2);

            // Fail before parsing if the
            if (strlen($headers) > $this->maxSize) {
                $this->headerSizeExceeded();
                return;
            }

            $this->stream->removeListener('data', [$this, 'feed']);
            $this->request = $this->parseHeaders($headers . "\r\n\r\n");

            $this->emit('headers', array($this->request, $buffer));
            $this->removeAllListeners();
        }

        // fail if the header hasn't finished but it is already too large
        if (!$this->request && strlen($this->buffer) > $this->maxSize) {
            $this->headerSizeExceeded();
            return;
        }
    }

    protected function headerSizeExceeded()
    {
        $this->emit('error', array(new \OverflowException("Maximum header size of {$this->maxSize} exceeded."), $this));
    }

    public function parseHeaders($data)
    {
        $psrRequest = gPsr\parse_request($data);

        $parsedQuery = [];
        $queryString = $psrRequest->getUri()->getQuery();
        if ($queryString) {
            parse_str($queryString, $parsedQuery);
        }

        $headers = array_map(function($val) {
            if (1 === count($val)) {
                $val = $val[0];
            }

            return $val;
        }, $psrRequest->getHeaders());

        return new Request(
            $psrRequest->getMethod(),
            $psrRequest->getUri(),
            $parsedQuery,
            $psrRequest->getProtocolVersion(),
            $headers
        );
    }
}
