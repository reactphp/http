<?php

namespace React\Http;

use Evenement\EventEmitter;
use Exception;
use RingCentral\Psr7 as g7;

/**
 * @event headers
 * @event error
 *
 * @internal
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

    private function parseAndEmitRequest()
    {
        list($request, $bodyBuffer) = $this->parseRequest($this->buffer);
        $this->emit('headers', array($request, $bodyBuffer));
    }

    private function parseRequest($data)
    {
        list($headers, $bodyBuffer) = explode("\r\n\r\n", $data, 2);

        $request = g7\parse_request($headers);

        return array($request, $bodyBuffer);
    }
}
