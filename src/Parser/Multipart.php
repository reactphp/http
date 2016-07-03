<?php

namespace React\Http\Parser;

use Evenement\EventEmitterTrait;
use React\Http\File;
use React\Http\Request;
use React\Stream\ThroughStream;

class Multipart implements ParserInterface
{
    use EventEmitterTrait;

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
     * @var Request
     */
    protected $request;


    public function __construct(Request $request)
    {
        $this->request = $request;
        $headers = $this->request->getHeaders();
        $headers = array_change_key_case($headers, CASE_LOWER);
        preg_match('/boundary="?(.*)"?$/', $headers['content-type'], $matches);

        $dataMethod = 'findBoundary';
        if (isset($matches[1])) {
            $this->setBoundary($matches[1]);
            $dataMethod = 'onData';
        }
        $this->request->on('data', [$this, $dataMethod]);
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
            $this->request->removeListener('data', [$this, 'findBoundary']);
            $this->request->on('data', [$this, 'onData']);
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
            $this->parseChunk(ltrim($chunk));
        }

        $split = explode("\r\n\r\n", $this->buffer);
        if (count($split) <= 1) {
            return;
        }

        $chunks = preg_split('/-+' . $this->boundary . '/', trim($split[0]), -1, PREG_SPLIT_NO_EMPTY);
        $headers = $this->parseHeaders(trim($chunks[0]));
        if (isset($headers['content-disposition']) && $this->headerStartsWith($headers['content-disposition'], 'filename')) {
            $this->parseFile($headers, $split[1]);
            $this->buffer = '';
        }
    }

    protected function parseChunk($chunk)
    {
        if ($chunk == '') {
            return;
        }

        list ($header, $body) = explode("\r\n\r\n", $chunk);
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
        $this->emit('file', [
            new File(
                $this->getFieldFromHeader($headers['content-disposition'], 'name'),
                $this->getFieldFromHeader($headers['content-disposition'], 'filename'),
                $headers['content-type'][0],
                $stream
            ),
            $headers,
        ]);

        if (!$streaming) {
            $stream->end($body);
            return;
        }

        $this->request->removeListener('data', [$this, 'onData']);
        $this->request->on('data', $this->chunkStreamFunc($stream));
        $stream->write($body);
    }

    protected function chunkStreamFunc(ThroughStream $stream)
    {
        $buffer = '';
        $func = function($data) use (&$func, &$buffer, $stream) {
            $buffer .= $data;
            if (strpos($buffer, $this->boundary) !== false) {
                $chunks = preg_split('/-+' . $this->boundary . '/', $buffer);
                $chunk = array_shift($chunks);
                $stream->end($chunk);

                $this->request->removeListener('data', $func);
                $this->request->on('data', [$this, 'onData']);

                if (count($chunks) == 1) {
                    array_unshift($chunks, '');
                }

                $this->onData(implode('-' . $this->boundary, $chunks));
                return;
            }

            if (strlen($buffer) >= strlen($this->boundary) * 3) {
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
                $this->emit('post', [
                    $matches[1],
                    trim($body),
                    $headers,
                ]);
            }
        }
    }

    protected function parseHeaders($header)
    {
        $headers = [];

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
}
