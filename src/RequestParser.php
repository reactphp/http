<?php

namespace React\Http;

use Evenement\EventEmitter;
use GuzzleHttp\Psr7 as g7;

/**
 * @event headers
 * @event error
 */
class RequestParser extends EventEmitter
{
    private $buffer = '';
    private $maxSize = 4096;

    /**
     * @var Request
     */
    private $request;
    private $length = 0;

    public function feed($data)
    {
        $this->buffer .= $data;

        if (!$this->request && false !== strpos($this->buffer, "\r\n\r\n")) {

            // Extract the header from the buffer
            // in case the content isn't complete
            list($headers, $this->buffer) = explode("\r\n\r\n", $this->buffer, 2);

            // Fail before parsing if the
            if (strlen($headers) > $this->maxSize) {
                $this->headerSizeExceeded();
                return;
            }

            $this->request = $this->parseHeaders($headers . "\r\n\r\n");
        }

        // if there is a request (meaning the headers are parsed) and
        // we have the right content size, we can finish the parsing
        if ($this->request && $this->isRequestComplete()) {
            $this->parseBody(substr($this->buffer, 0, $this->length));
            $this->finishParsing();
            return;
        }

        // fail if the header hasn't finished but it is already too large
        if (!$this->request && strlen($this->buffer) > $this->maxSize) {
            $this->headerSizeExceeded();
            return;
        }
    }

    protected function isRequestComplete()
    {
        $headers = $this->request->getHeaders();

        // if there is no content length, there should
        // be no content so we can say it's done
        if (!array_key_exists('Content-Length', $headers)) {
            return true;
        }

        // if the content is present and has the
        // right length, we're good to go
        if (array_key_exists('Content-Length', $headers) && strlen($this->buffer) >= $headers['Content-Length']) {

            // store the expected content length
            $this->length = $this->request->getHeaders()['Content-Length'];

            return true;
        }

        return false;
    }

    protected function finishParsing()
    {
        $this->emit('headers', array($this->request, $this->request->getBody()));
        $this->removeAllListeners();
        $this->request = null;
    }

    protected function headerSizeExceeded()
    {
        $this->emit('error', array(new \OverflowException("Maximum header size of {$this->maxSize} exceeded."), $this));
    }

    public function parseHeaders($data)
    {
        $psrRequest = g7\parse_request($data);

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

        return new Request(
            $psrRequest->getMethod(),
            $psrRequest->getUri(),
            $parsedQuery,
            $psrRequest->getProtocolVersion(),
            $headers
        );
    }

    public function parseBody($content)
    {
        $headers = $this->request->getHeaders();

        if (array_key_exists('Content-Type', $headers)) {
            if (strpos($headers['Content-Type'], 'multipart/') === 0) {
                //TODO :: parse the content while it is streaming
                preg_match("/boundary=\"?(.*)\"?$/", $headers['Content-Type'], $matches);
                $boundary = $matches[1];

                $parser = new MultipartParser($content, $boundary);
                $parser->parse();

                $this->request->setPost($parser->getPost());
                $this->request->setFiles($parser->getFiles());
                return;
            }

            if (strtolower($headers['Content-Type']) == 'application/x-www-form-urlencoded') {
                parse_str(urldecode($content), $result);
                $this->request->setPost($result);

                return;
            }
        }



        $this->request->setBody($content);
    }
}
