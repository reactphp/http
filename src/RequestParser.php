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
    private $parsingDone;

    public function feed($data)
    {
        if (strlen($this->buffer) + strlen($data) > $this->maxSize) {
            $this->emit('error', array(new \OverflowException("Maximum header size of {$this->maxSize} exceeded."), $this));

            return;
        }

        $this->buffer .= $data;

        if (false !== strpos($this->buffer, "\r\n\r\n")) {
            $this->parseRequest($this->buffer);
        }
    }

    public function parseRequest($data)
    {
        $this->parsingDone = false;
        $parser = new MessageParser();
        $parsed = $parser->parseRequest($data);

        $parsedQuery = array();
        if ($parsed['request_url']['query']) {
            parse_str($parsed['request_url']['query'], $parsedQuery);
        }

        $request = new Request(
            $parsed['method'],
            $parsed['request_url']['path'],
            $parsedQuery,
            array(),
            array(),
            $parsed['version'],
            $parsed['headers']
        );

        $request->on('parsed' , function() use($request) {
            $this->emit('request', array($request));
            $this->removeAllListeners();
        });

        if($request->isMultiPart()) {
            $this->parseMultipart($request, $parsed['body']);
        }
        else {
            $request->body = $parsed['body'];
            $this->parsingDone = true;
        }

        if($this->parsingDone === true) {
            $this->emit('request', array($request));
            $this->removeAllListeners();
        }
    }

    private function parseMultiPart($request, $body)
    {
        $boundary = $this->extractBoundary($request);

        $blocks = $this->sliceBody($body, $boundary);
        array_pop($blocks);

        $file_count = 0;

        foreach($blocks as $block) {
            if(empty($block))
                continue;
            if (strpos($block, 'application/octet-stream') !== FALSE)
            {
                preg_match("/name=\"([^\"]*)\"; filename=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s", $block, $matches);
                $file_count++;
                $temp_name = tempnam(sys_get_temp_dir(), 'Reactor');
                $this->emit('trigger', array(function($loop) use($request, $temp_name, $file_count, $matches) {
                    \React\Filesystem\Filesystem::create($loop)->file($temp_name)->open('cw')->then(function ($stream) use($request, $temp_name, $file_count, $matches){
                        $stream->write($matches[3]);
                        $stream->close();
                        $request->addFile($matches[1], $matches[2], $temp_name, UPLOAD_ERR_OK, strlen($matches[3]));
                        $file_count--;
                        if($file_count === 0) {
                            $this->parsingDone = true;
                            $request->emit('parsed', array());
                        }
                    });
                }));
            }
            else
            {
                preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
                $request->addPost($matches[1], $matches[2]);
            }
        }

        if($file_count === 0) {
            $this->parsingDone = true;
        }
    }

    private function sliceBody($body, $boundary)
    {
        $blocks = preg_split("/-+$boundary/", $body);

        return $blocks;
    }

    private function extractBoundary($request)
    {
        $type = $request->getContentType();

        preg_match('/boundary=(.*)$/', $type, $matches);

        if(!count($matches)) {
            throw new \Exception('Cannot extract boundary. Boundary missing.');
        }

        return $matches[1];
    }
}
