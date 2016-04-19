<?php

namespace React\Http\Parser;

use Evenement\EventEmitterTrait;
use React\Http\Request;

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
            $this->setBoundary(substr($this->buffer, 0, strpos($this->buffer, "\r\n")));
            $this->request->removeListener('data', [$this, 'findBoundary']);
            $this->request->on('data', [$this, 'onData']);
        }
    }

    public function onData($data)
    {
        $this->buffer .= $data;

        if (
            strpos($this->buffer, $this->boundary) < strrpos($this->buffer, "\r\n\r\n") ||
            substr($this->buffer, -1, $this->endingSize) === $this->ending
        ) {
            $this->parseBuffer();
        }
    }

    protected function parseBuffer()
    {
        $chunks = explode($this->boundary, $this->buffer);
        $this->buffer = array_pop($chunks);
        foreach ($chunks as $chunk) {
            $this->parseChunk(ltrim($chunk));
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

        if ($this->headerStartsWith($headers['content-disposition'], 'name')) {
            $this->parsePost($headers, $body);
            return;
        }
    }

    protected function parsePost($headers, $body)
    {
        foreach ($headers['content-disposition'] as $part) {
            if (strpos($part, 'name') === 0) {
                preg_match('/name="?(.*)"?$/', $part, $matches);
                $this->emit('post', [
                    $matches[1],
                    $body,
                    $headers,
                ]);
            }
        }
    }

    protected function parseHeaders($header)
    {
        $headers = [];

        foreach (explode("\r\n", $header) as $line) {
            list($key, $values) = explode(':', $line);
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
}
