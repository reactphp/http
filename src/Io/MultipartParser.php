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
 * @link https://tools.ietf.org/html/rfc7578
 * @link https://tools.ietf.org/html/rfc2046#section-5.1.1
 */
final class MultipartParser
{
    /**
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * @var int|null
     */
    protected $maxFileSize;

    /**
     * ini setting "max_input_vars"
     *
     * Does not exist in PHP < 5.3.9 or HHVM, so assume PHP's default 1000 here.
     *
     * @var int
     * @link http://php.net/manual/en/info.configuration.php#ini.max-input-vars
     */
    private $maxInputVars = 1000;

    /**
     * ini setting "max_input_nesting_level"
     *
     * Does not exist in HHVM, but assumes hard coded to 64 (PHP's default).
     *
     * @var int
     * @link http://php.net/manual/en/info.configuration.php#ini.max-input-nesting-level
     */
    private $maxInputNestingLevel = 64;

    /**
     * ini setting "upload_max_filesize"
     *
     * @var int
     */
    private $uploadMaxFilesize;

    private $postCount = 0;

    public function __construct()
    {
        $var = ini_get('max_input_vars');
        if ($var !== false) {
            $this->maxInputVars = (int)$var;
        }
        $var = ini_get('max_input_nesting_level');
        if ($var !== false) {
            $this->maxInputNestingLevel = (int)$var;
        }

        $this->uploadMaxFilesize = $this->iniUploadMaxFilesize();
    }

    public function parse(ServerRequestInterface $request)
    {
        $contentType = $request->getHeaderLine('content-type');
        if(!preg_match('/boundary="?(.*)"?$/', $contentType, $matches)) {
            return $request;
        }

        $this->request = $request;
        $this->parseBody('--' . $matches[1], (string)$request->getBody());

        $request = $this->request;
        $this->request = null;
        $this->postCount = 0;
        $this->maxFileSize = null;

        return $request;
    }

    private function parseBody($boundary, $buffer)
    {
        $len = strlen($boundary);

        // ignore everything before initial boundary (SHOULD be empty)
        $start = strpos($buffer, $boundary . "\r\n");

        while ($start !== false) {
            // search following boundary (preceded by newline)
            // ignore last if not followed by boundary (SHOULD end with "--")
            $start += $len + 2;
            $end = strpos($buffer, "\r\n" . $boundary, $start);
            if ($end === false) {
                break;
            }

            // parse one part and continue searching for next
            $this->parsePart(substr($buffer, $start, $end - $start));
            $start = $end;
        }
    }

    private function parsePart($chunk)
    {
        $pos = strpos($chunk, "\r\n\r\n");
        if ($pos === false) {
            return;
        }

        $headers = $this->parseHeaders((string)substr($chunk, 0, $pos));
        $body = (string)substr($chunk, $pos + 4);

        if (!isset($headers['content-disposition'])) {
            return;
        }

        $name = $this->getParameterFromHeader($headers['content-disposition'], 'name');
        if ($name === null) {
            return;
        }

        $filename = $this->getParameterFromHeader($headers['content-disposition'], 'filename');
        if ($filename !== null) {
            $this->parseFile(
                $name,
                $filename,
                isset($headers['content-type'][0]) ? $headers['content-type'][0] : null,
                $body
            );
        } else {
            $this->parsePost($name, $body);
        }
    }

    private function parseFile($name, $filename, $contentType, $contents)
    {
        $this->request = $this->request->withUploadedFiles($this->extractPost(
            $this->request->getUploadedFiles(),
            $name,
            $this->parseUploadedFile($filename, $contentType, $contents)
        ));
    }

    private function parseUploadedFile($filename, $contentType, $contents)
    {
        $size = strlen($contents);

        // no file selected (zero size and empty filename)
        if ($size === 0 && $filename === '') {
            return new UploadedFile(
                Psr7\stream_for(''),
                $size,
                UPLOAD_ERR_NO_FILE,
                $filename,
                $contentType
            );
        }

        // file exceeds "upload_max_filesize" ini setting
        if ($size > $this->uploadMaxFilesize) {
            return new UploadedFile(
                Psr7\stream_for(''),
                $size,
                UPLOAD_ERR_INI_SIZE,
                $filename,
                $contentType
            );
        }

        // file exceeds MAX_FILE_SIZE value
        if ($this->maxFileSize !== null && $size > $this->maxFileSize) {
            return new UploadedFile(
                Psr7\stream_for(''),
                $size,
                UPLOAD_ERR_FORM_SIZE,
                $filename,
                $contentType
            );
        }

        return new UploadedFile(
            Psr7\stream_for($contents),
            $size,
            UPLOAD_ERR_OK,
            $filename,
            $contentType
        );
    }

    private function parsePost($name, $value)
    {
        // ignore excessive number of post fields
        if (++$this->postCount > $this->maxInputVars) {
            return;
        }

        $this->request = $this->request->withParsedBody($this->extractPost(
            $this->request->getParsedBody(),
            $name,
            $value
        ));

        if (strtoupper($name) === 'MAX_FILE_SIZE') {
            $this->maxFileSize = (int)$value;

            if ($this->maxFileSize === 0) {
                $this->maxFileSize = null;
            }
        }
    }

    private function parseHeaders($header)
    {
        $headers = array();

        foreach (explode("\r\n", trim($header)) as $line) {
            $parts = explode(':', $line, 2);
            if (!isset($parts[1])) {
                continue;
            }

            $key = strtolower(trim($parts[0]));
            $values = explode(';', $parts[1]);
            $values = array_map('trim', $values);
            $headers[$key] = $values;
        }

        return $headers;
    }

    private function getParameterFromHeader(array $header, $parameter)
    {
        foreach ($header as $part) {
            if (preg_match('/' . $parameter . '="?(.*)"$/', $part, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function extractPost($postFields, $key, $value)
    {
        $chunks = explode('[', $key);
        if (count($chunks) == 1) {
            $postFields[$key] = $value;
            return $postFields;
        }

        // ignore this key if maximum nesting level is exceeded
        if (isset($chunks[$this->maxInputNestingLevel])) {
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

    /**
     * Gets upload_max_filesize from PHP's configuration expressed in bytes
     *
     * @return int
     * @link http://php.net/manual/en/ini.core.php#ini.upload-max-filesize
     * @codeCoverageIgnore
     */
    private function iniUploadMaxFilesize()
    {
        $size = ini_get('upload_max_filesize');
        $suffix = strtoupper(substr($size, -1));
        if ($suffix === 'K') {
            return substr($size, 0, -1) * 1024;
        }
        if ($suffix === 'M') {
            return substr($size, 0, -1) * 1024 * 1024;
        }
        if ($suffix === 'G') {
            return substr($size, 0, -1) * 1024 * 1024 * 1024;
        }

        return $size;
    }
}
