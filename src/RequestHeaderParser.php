<?php

namespace React\Http;

use Evenement\EventEmitter;
use Exception;
use RingCentral\Psr7 as g7;

/**
 * @event headers
 * @event error
 */
class RequestHeaderParser extends EventEmitter
{
    private $buffer = '';
    private $maxSize = 4096;

    public function feed($data)
    {
        $this->buffer .= $data;

        $endOfHeader = strpos($this->buffer, "\r\n\r\n");

        if (false !== $endOfHeader) {
            $currentHeaderSize = $endOfHeader;
        } else {
            $currentHeaderSize = strlen($this->buffer);
        }

        if ($currentHeaderSize > $this->maxSize) {
            $this->emit('error', array(new \OverflowException("Maximum header size of {$this->maxSize} exceeded."), $this));
            $this->removeAllListeners();
            return;
        }

        if (false !== $endOfHeader) {
            try {
                $this->parseAndEmitRequest();
            } catch (Exception $exception) {
                $this->emit('error', array($exception));
            }
            $this->removeAllListeners();
        }
    }

    protected function parseAndEmitRequest()
    {
        list($request, $bodyBuffer) = $this->parseRequest($this->buffer);
        $this->emit('headers', array($request, $bodyBuffer));
    }

    public function parseRequest($data)
    {
        list($headers, $bodyBuffer) = explode("\r\n\r\n", $data, 2);

        $psrRequest = g7\parse_request($headers);

        $parsedQuery = array();
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

        $request = new Request(
            $psrRequest->getMethod(),
            $psrRequest->getUri()->getPath(),
            $parsedQuery,
            $psrRequest->getProtocolVersion(),
            $headers
        );

        return array($request, $bodyBuffer);
    }
}
