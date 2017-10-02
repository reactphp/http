<?php

namespace React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\HttpBodyStream;
use React\Http\UploadedFile;
use RingCentral\Psr7;

/**
 * @internal
 */
final class MultipartParser
{
    /**
     * @var string
     */
    protected $buffer = '';

    /**
     * @var string
     */
    protected $boundary;

    /**
     * @var ServerRequestInterface
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

    public static function parseRequest(ServerRequestInterface $request)
    {
        $parser = new self($request);
        return $parser->parse();
    }

    private function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    private function parse()
    {
        $this->buffer = (string)$this->request->getBody();

        $this->determineStartMethod();

        return $this->request;
    }

    private function determineStartMethod()
    {
        if (!$this->request->hasHeader('content-type')) {
            $this->findBoundary();
            return;
        }

        $contentType = $this->request->getHeaderLine('content-type');
        preg_match('/boundary="?(.*)"?$/', $contentType, $matches);
        if (isset($matches[1])) {
            $this->boundary = $matches[1];
            $this->parseBuffer();
            return;
        }

        $this->findBoundary();
    }

    private function findBoundary()
    {
        if (substr($this->buffer, 0, 3) === '---' && strpos($this->buffer, "\r\n") !== false) {
            $boundary = substr($this->buffer, 2, strpos($this->buffer, "\r\n"));
            $boundary = substr($boundary, 0, -2);
            $this->boundary = $boundary;
            $this->parseBuffer();
        }
    }

    private function parseBuffer()
    {
        $chunks = explode('--' . $this->boundary, $this->buffer);
        $this->buffer = array_pop($chunks);
        foreach ($chunks as $chunk) {
            $chunk = $this->stripTrailingEOL($chunk);
            $this->parseChunk($chunk);
        }
    }

    private function parseChunk($chunk)
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
            $this->parseFile($headers, $body);
            return;
        }

        if ($this->headerStartsWith($headers['content-disposition'], 'name')) {
            $this->parsePost($headers, $body);
            return;
        }
    }

    private function parseFile($headers, $body)
    {
        if (
            !$this->headerContains($headers['content-disposition'], 'name=') ||
            !$this->headerContains($headers['content-disposition'], 'filename=')
        ) {
            return;
        }

        $this->request = $this->request->withUploadedFiles($this->extractPost(
            $this->request->getUploadedFiles(),
            $this->getFieldFromHeader($headers['content-disposition'], 'name'),
            new UploadedFile(
                Psr7\stream_for($body),
                strlen($body),
                UPLOAD_ERR_OK,
                $this->getFieldFromHeader($headers['content-disposition'], 'filename'),
                $headers['content-type'][0]
            )
        ));
    }

    private function parsePost($headers, $body)
    {
        foreach ($headers['content-disposition'] as $part) {
            if (strpos($part, 'name') === 0) {
                preg_match('/name="?(.*)"$/', $part, $matches);
                $this->request = $this->request->withParsedBody($this->extractPost(
                    $this->request->getParsedBody(),
                    $matches[1],
                    $body
                ));
            }
        }
    }

    private function parseHeaders($header)
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

    private function headerStartsWith(array $header, $needle)
    {
        foreach ($header as $part) {
            if (strpos($part, $needle) === 0) {
                return true;
            }
        }

        return false;
    }

    private function headerContains(array $header, $needle)
    {
        foreach ($header as $part) {
            if (strpos($part, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function getFieldFromHeader(array $header, $field)
    {
        foreach ($header as $part) {
            if (strpos($part, $field) === 0) {
                preg_match('/' . $field . '="?(.*)"$/', $part, $matches);
                return $matches[1];
            }
        }

        return '';
    }

    private function stripTrailingEOL($chunk)
    {
        if (substr($chunk, -2) === "\r\n") {
            return substr($chunk, 0, -2);
        }

        return $chunk;
    }

    private function extractPost($postFields, $key, $value)
    {
        $chunks = explode('[', $key);
        if (count($chunks) == 1) {
            $postFields[$key] = $value;
            return $postFields;
        }

        $chunkKey = $chunks[0];
        if (!isset($postFields[$chunkKey])) {
            $postFields[$chunkKey] = array();
        }

        $parent = &$postFields;
        for ($i = 1; $i < count($chunks); $i++) {
            $previousChunkKey = $chunkKey;
            if (!isset($parent[$previousChunkKey])) {
                $parent[$previousChunkKey] = array();
            }
            $parent = &$parent[$previousChunkKey];
            $chunkKey = $chunks[$i];

            if ($chunkKey == ']') {
                $parent[] = $value;
                return $postFields;
            }

            $chunkKey = rtrim($chunkKey, ']');
            if ($i == count($chunks) - 1) {
                $parent[$chunkKey] = $value;
                return $postFields;
            }
        }

        return $postFields;
    }
}
