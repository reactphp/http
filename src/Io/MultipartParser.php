<?php

namespace React\Http\Io;

use Psr\Http\Message\ServerRequestInterface;
use RingCentral\Psr7;

/**
 * [Internal] Parses a string body with "Content-Type: multipart/form-data" into structured data
 *
 * This is use internally to parse incoming request bodies into structured data
 * that resembles PHP's `$_POST` and `$_FILES` superglobals.
 *
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

    /**
     * @var int|null
     */
    protected $maxFileSize;

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

        if (!$this->headerContainsParameter($headers['content-disposition'], 'name')) {
            return;
        }

        if ($this->headerContainsParameter($headers['content-disposition'], 'filename')) {
            $this->parseFile($headers, $body);
        } else {
            $this->parsePost($headers, $body);
        }
    }

    private function parseFile($headers, $body)
    {
        $this->request = $this->request->withUploadedFiles($this->extractPost(
            $this->request->getUploadedFiles(),
            $this->getParameterFromHeader($headers['content-disposition'], 'name'),
            $this->parseUploadedFile($headers, $body)
        ));
    }

    private function parseUploadedFile($headers, $body)
    {
        $filename = $this->getParameterFromHeader($headers['content-disposition'], 'filename');
        $bodyLength = strlen($body);
        $contentType = isset($headers['content-type'][0]) ? $headers['content-type'][0] : null;

        // no file selected (zero size and empty filename)
        if ($bodyLength === 0 && $filename === '') {
            return new UploadedFile(
                Psr7\stream_for(''),
                $bodyLength,
                UPLOAD_ERR_NO_FILE,
                $filename,
                $contentType
            );
        }

        // file exceeds MAX_FILE_SIZE value
        if ($this->maxFileSize !== null && $bodyLength > $this->maxFileSize) {
            return new UploadedFile(
                Psr7\stream_for(''),
                $bodyLength,
                UPLOAD_ERR_FORM_SIZE,
                $filename,
                $contentType
            );
        }

        return new UploadedFile(
            Psr7\stream_for($body),
            $bodyLength,
            UPLOAD_ERR_OK,
            $filename,
            $contentType
        );
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

                if (strtoupper($matches[1]) === 'MAX_FILE_SIZE') {
                    $this->maxFileSize = (int)$body;

                    if ($this->maxFileSize === 0) {
                        $this->maxFileSize = null;
                    }
                }
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

    private function headerContainsParameter(array $header, $parameter)
    {
        foreach ($header as $part) {
            if (strpos($part, $parameter . '=') === 0) {
                return true;
            }
        }

        return false;
    }

    private function getParameterFromHeader(array $header, $parameter)
    {
        foreach ($header as $part) {
            if (strpos($part, $parameter) === 0) {
                preg_match('/' . $parameter . '="?(.*)"$/', $part, $matches);
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

        $chunkKey = rtrim($chunks[0], ']');
        $parent = &$postFields;
        for ($i = 1; isset($chunks[$i]); $i++) {
            $previousChunkKey = $chunkKey;

            if ($previousChunkKey === '') {
                $parent[] = array();
                end($parent);
                $parent = &$parent[key($parent)];
            } else {
                if (!isset($parent[$previousChunkKey]) || !is_array($parent[$previousChunkKey])) {
                    $parent[$previousChunkKey] = array();
                }
                $parent = &$parent[$previousChunkKey];
            }

            $chunkKey = rtrim($chunks[$i], ']');
        }

        if ($chunkKey === '') {
            $parent[] = $value;
        } else {
            $parent[$chunkKey] = $value;
        }

        return $postFields;
    }
}
