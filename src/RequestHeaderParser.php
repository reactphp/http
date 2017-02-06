<?php

namespace React\Http;

use Evenement\EventEmitter;
use Exception;
use GuzzleHttp\Psr7 as g7;

/**
 * @event headers
 * @event error
 */
class RequestHeaderParser extends EventEmitter
{
    /**
     * While HTTP does not strictly specify max length for headers,
     * most HTTP-server implementations use 8K. There are some using
     * 4K though (eg nginx use system page size, that is usually around
     * 4096 bytes)
     */
    const DEFAULT_HEADER_SIZE = 4096;

    /**
     * Data buffer used to emit "data" event if we have read
     * part of body while seeking headers end (\r\n\r\n)
     *
     * @var string
     */
    private $buffer = '';

    /**
     * Maximum headers length
     *
     * @var int
     */
    private $maxSize;

    /**
     * @param int $maxSize
     */
    public function __construct(
        $maxSize = self::DEFAULT_HEADER_SIZE
    ) {
        $this->maxSize = $maxSize;
    }

    public function feed($data)
    {
        try {
            $this->buffer .= $data;

            $headerLen = strpos($this->buffer, "\r\n\r\n");

            if (($headerLen ?: strlen($this->buffer)) > $this->maxSize) {
                throw new \OverflowException("Maximum header size of {$this->maxSize} exceeded.");
            }

            if (false !== $headerLen) {
                $this->parseAndEmitRequest();
                $this->removeAllListeners();
            }
        } catch (Exception $exception) {
            $this->emit('error', [$exception, $this]);
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

        $request = new Request(
            $psrRequest->getMethod(),
            $psrRequest->getUri()->getPath(),
            $parsedQuery,
            $psrRequest->getProtocolVersion(),
            $headers
        );

        return array($request, $bodyBuffer);
    }

    /**
     * @return int
     */
    public function getMaxHeadersSize()
    {
        return $this->maxSize;
    }
}
