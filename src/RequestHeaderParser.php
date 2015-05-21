<?php

namespace React\Http;

use Evenement\EventEmitter;
use GuzzleHttp\Psr7 as g7;

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
        if (strlen($this->buffer) + strlen($data) > $this->maxSize) {
            $this->emit('error', array(new \OverflowException("Maximum header size of {$this->maxSize} exceeded."), $this));

            return;
        }

        $this->buffer .= $data;

        if (false !== strpos($this->buffer, "\r\n\r\n")) {
            list($request, $bodyBuffer) = $this->parseRequest($this->buffer);

            $this->emit('headers', array($request, $bodyBuffer));
            $this->removeAllListeners();
        }
    }

    public function parseRequest($data)
    {
        list($headers, $bodyBuffer) = explode("\r\n\r\n", $data, 2);

        $psrRequest = g7\parse_request($headers);

        $parsedQuery = [];
        $queryString = $psrRequest->getUri()->getQuery();
        if ($queryString) {
            parse_str($queryString, $parsedQuery);
        }

        $headers = $psrRequest->getHeaders();
        array_walk($headers, function(&$val) {
            if (1 === count($val)) {
                $val = $val[0];
            }
        });

        $request = new Request(
            $psrRequest->getMethod(),
            $psrRequest ->getUri()->getPath(),
            $parsedQuery,
            $psrRequest->getProtocolVersion(),
            $headers
        );

        return array($request, $bodyBuffer);
    }
}
