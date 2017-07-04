<?php

namespace React\Http\StreamingBodyParser;

use Evenement\EventEmitter;
use Psr\Http\Message\RequestInterface;
use React\Http\HttpBodyStream;
use React\Http\UploadedFile;
use React\Stream\ThroughStream;

final class MultipartParser extends EventEmitter
{
    /**
     * @var string
     */
    protected $buffer = '';

    /**
     * @var string
     */
    protected $ending = '';

    /**
     * @var int
     */
    protected $endingSize = 0;

    /**
     * @var string
     */
    protected $boundary;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var HttpBodyStream
     */
    protected $body;

    /**
     * @var callable
     */
    protected $onDataCallable;

    public function __construct(RequestInterface $request)
    {
        $this->onDataCallable = array($this, 'onData');
        $this->request = $request;
        $this->body = $this->request->getBody();
        $dataMethod = $this->determineOnDataMethod();
        $this->setOnDataListener(array($this, $dataMethod));
    }

    protected function determineOnDataMethod()
    {
        if (!$this->request->hasHeader('content-type')) {
            return 'findBoundary';
        }

        $contentType = $this->request->getHeaderLine('content-type');
        preg_match('/boundary="?(.*)"?$/', $contentType, $matches);
        if (isset($matches[1])) {
            $this->setBoundary($matches[1]);
            return 'onData';
        }

        return 'findBoundary';
    }

    protected function setBoundary($boundary)
    {
        $this->boundary = $boundary;
        $this->ending = $this->boundary . "--\r\n";
        $this->endingSize = strlen($this->ending);
    }

    public function findBoundary($data)
    {
        $this->buffer .= $data;

        if (substr($this->buffer, 0, 3) === '---' && strpos($this->buffer, "\r\n") !== false) {
            $boundary = substr($this->buffer, 2, strpos($this->buffer, "\r\n"));
            $boundary = substr($boundary, 0, -2);
            $this->setBoundary($boundary);
            $this->setOnDataListener(array($this, 'onData'));
        }
    }

    public function onData($data)
    {
        $this->buffer .= $data;
        $ending = strpos($this->buffer, $this->ending) == strlen($this->buffer) - $this->endingSize;

        if (
            strrpos($this->buffer, $this->boundary) < strrpos($this->buffer, "\r\n\r\n") || $ending
        ) {
            $this->parseBuffer();
        }

        if ($ending) {
            $this->emit('end');
        }
    }

    protected function parseBuffer()
    {
        $chunks = preg_split('/-+' . $this->boundary . '/', $this->buffer);
        $this->buffer = array_pop($chunks);
        foreach ($chunks as $chunk) {
            $chunk = $this->stripTrailingEOL($chunk);
            $this->parseChunk($chunk);
        }

        $split = explode("\r\n\r\n", $this->buffer, 2);
        if (count($split) <= 1) {
            return;
        }

        $chunks = preg_split('/-+' . $this->boundary . '/', $split[0], -1, PREG_SPLIT_NO_EMPTY);
        $headers = $this->parseHeaders(trim($chunks[0]));
        if (isset($headers['content-disposition']) && $this->headerStartsWith($headers['content-disposition'], 'filename')) {
            $this->parseFile($headers, $split[1]);
            $this->buffer = '';
        }
    }

    protected function parseChunk($chunk)
    {
        if ($chunk === '') {
            return;
        }

        list ($header, $body) = explode("\r\n\r\n", $chunk, 2);
        $headers = $this->parseHeaders($header);

        if (!isset($headers['content-disposition'])) {
            return;
        }

        if ($this->headerStartsWith($headers['content-disposition'], 'filename')) {
            $this->parseFile($headers, $body, false);
            return;
        }

        if ($this->headerStartsWith($headers['content-disposition'], 'name')) {
            $this->parsePost($headers, $body);
            return;
        }
    }

    protected function parseFile($headers, $body, $streaming = true)
    {
        if (
            !$this->headerContains($headers['content-disposition'], 'name=') ||
            !$this->headerContains($headers['content-disposition'], 'filename=')
        ) {
            return;
        }

        $stream = new ThroughStream();
        $this->emit('file', array(
            $this->getFieldFromHeader($headers['content-disposition'], 'name'),
            new UploadedFile(
                new HttpBodyStream($stream, null),
                0,
                UPLOAD_ERR_OK,
                $this->getFieldFromHeader($headers['content-disposition'], 'filename'),
                $headers['content-type'][0]
            ),
            $headers,
        ));

        if (!$streaming) {
            $stream->end($body);
            return;
        }

        $this->setOnDataListener($this->chunkStreamFunc($stream));
        $stream->write($body);
    }

    protected function chunkStreamFunc(ThroughStream $stream)
    {
        $that = $this;
        $boundary = $this->boundary;
        $buffer = '';
        $func = function($data) use (&$buffer, $stream, $that, $boundary) {
            $buffer .= $data;
            if (strpos($buffer, $boundary) !== false) {
                $chunks = preg_split('/-+' . $boundary . '/', $buffer);
                $chunk = array_shift($chunks);
                $chunk = $that->stripTrailingEOL($chunk);
                $stream->end($chunk);

                $that->setOnDataListener(array($that, 'onData'));

                if (count($chunks) == 1) {
                    array_unshift($chunks, '');
                }

                $that->onData(implode('-' . $boundary, $chunks));
                return;
            }

            if (strlen($buffer) >= strlen($boundary) * 3) {
                $stream->write($buffer);
                $buffer = '';
            }
        };
        return $func;
    }

    protected function parsePost($headers, $body)
    {
        foreach ($headers['content-disposition'] as $part) {
            if (strpos($part, 'name') === 0) {
                preg_match('/name="?(.*)"$/', $part, $matches);
                $this->emit('post', array(
                    $matches[1],
                    $body,
                    $headers,
                ));
            }
        }
    }

    protected function parseHeaders($header)
    {
        $headers = array();

        foreach (explode("\r\n", trim($header)) as $line) {
            list($key, $values) = explode(':', $line, 2);
            $key = trim($key);
            $key = strtolower($key);
            $values = explode(';', $values);
            $values = array_map('trim', $values);
            $headers[$key] = $values;
        }

        return $headers;
    }

    protected function headerStartsWith(array $header, $needle)
    {
        foreach ($header as $part) {
            if (strpos($part, $needle) === 0) {
                return true;
            }
        }

        return false;
    }

    protected function headerContains(array $header, $needle)
    {
        foreach ($header as $part) {
            if (strpos($part, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function getFieldFromHeader(array $header, $field)
    {
        foreach ($header as $part) {
            if (strpos($part, $field) === 0) {
                preg_match('/' . $field . '="?(.*)"$/', $part, $matches);
                return $matches[1];
            }
        }

        return '';
    }

    /**
     * @internal
     */
    public function setOnDataListener($callable)
    {
        $this->body->removeListener('data', $this->onDataCallable);
        $this->onDataCallable = $callable;
        $this->body->on('data', $this->onDataCallable);
    }

    public function cancel()
    {
        $this->body->removeListener('data', $this->onDataCallable);
        $this->body->close();
    }

    /**
     * @internal
     */
    public function stripTrailingEOL($chunk)
    {
        if (substr($chunk, -2) === "\r\n") {
            return substr($chunk, 0, -2);
        }

        return $chunk;
    }
}
