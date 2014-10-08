<?php

namespace React\Http;

use Evenement\EventEmitter;
use Guzzle\Parser\Message\MessageParser;

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
        $parser = new MessageParser();
        $parsed = $parser->parseRequest($data);

        $parsedQuery = array();
        if ($parsed['request_url']['query']) {
            parse_str($parsed['request_url']['query'], $parsedQuery);
        }

        return new Request(
            $parsed['method'],
            $parsed['request_url'],
            $parsedQuery,
            $parsed['version'],
            $parsed['headers']
        );
    }

    public function parseBody($content)
    {
        $headers = $this->request->getHeaders();

        if (array_key_exists('Content-Type', $headers)) {
            if (strpos($headers['Content-Type'], 'multipart/') === 0) {
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
