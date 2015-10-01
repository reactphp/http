<?php

namespace React\Http\Parser;

use React\Http\File;
use React\Http\Request;
use React\Stream\ReadableStreamInterface;
use React\Stream\ThroughStream;

/**
 * Parse a multipart body
 *
 * Original source is from https://gist.github.com/jas-/5c3fdc26fedd11cb9fb5
 *
 * @author jason.gerfen@gmail.com
 * @author stephane.goetz@onigoetz.ch
 * @license http://www.gnu.org/licenses/gpl.html GPL License 3
 */
class Multipart
{
    protected $buffer = '';

    /**
     * @var string
     */
    protected $stream;

    /**
     * @var string
     */
    protected $boundary;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @param ReadableStreamInterface $stream
     * @param string $boundary
     * @param Request $request
     */
    public function __construct(ReadableStreamInterface $stream, $boundary, Request $request)
    {
        $this->stream = $stream;
        $this->boundary = $boundary;
        $this->request = $request;

        $this->stream->on('data', [$this, 'feed']);
    }

    /**
     * Do the actual parsing
     *
     * @param string $data
     */
    public function feed($data)
    {
        $this->buffer .= $data;

        $ending = $this->boundary . "--\r\n";
        $endSize = strlen($ending);

        if (strpos($this->buffer, $this->boundary) < strrpos($this->buffer, "\r\n\r\n") || substr($this->buffer, -1, $endSize) === $ending) {
            $this->processBuffer();
        }
    }

    protected function processBuffer()
    {
        $chunks = preg_split("/\\-+$this->boundary/", $this->buffer);
        $this->buffer = array_pop($chunks);
        foreach ($chunks as $chunk) {
            $this->parseBlock($chunk);
        }

        $lines = explode("\r\n", $this->buffer);
        if (isset($lines[1]) && strpos($lines[1], 'filename') !== false) {
            $this->file($this->buffer);
            $this->buffer = '';
            return;
        }
    }

    /**
     * Decide if we handle a file, post value or octet stream
     *
     * @param $string string
     * @returns void
     */
    protected function parseBlock($string)
    {
        if ($string == '') {
            return;
        }

        list(, $firstLine) = explode("\r\n", $string);
        if (strpos($firstLine, 'filename') !== false) {
            $this->file($string, false);
            return;
        }

        // This may never be called, if an octet stream
        // has a filename it is catched by the previous
        // condition already.
        if (strpos($firstLine, 'application/octet-stream') !== false) {
            $this->octetStream($string);
            return;
        }

        $this->post($string);
    }

    /**
     * Parse a raw octet stream
     *
     * @param $string
     * @return array
     */
    protected function octetStream($string)
    {
        preg_match('/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s', $string, $match);

        $this->request->emit('post', [$match[1], $match[2]]);
    }

    /**
     * Parse a file
     *
     * @param string $firstChunk
     */
    protected function file($firstChunk, $streaming = true)
    {
        preg_match('/name=\"([^\"]*)\"; filename=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $firstChunk, $match);
        preg_match('/Content-Type: ([^\"]*)/', $match[3], $mime);

        $content = preg_replace('/Content-Type: (.*)[^\n\r]/', '', $match[3]);
        $content = ltrim($content, "\r\n");

        // Put content in a stream
        $stream = new ThroughStream();

        if ($streaming) {
            $this->stream->removeListener('data', [$this, 'feed']);
            $buffer = '';
            $func = function($data) use (&$func, &$buffer, $stream) {
                $buffer .= $data;
                if (strpos($buffer, $this->boundary) !== false) {
                    $chunks = preg_split("/\\-+$this->boundary/", $buffer);
                    $chunk = array_shift($chunks);
                    $stream->end($chunk);

                    $this->stream->removeListener('data', $func);
                    $this->stream->on('data', [$this, 'feed']);

                    $this->stream->emit('data', [implode($this->boundary, $chunks)]);
                    return;
                }

                if (strlen($buffer) >= strlen($this->boundary) * 3) {
                    $stream->write($buffer);
                    $buffer = '';
                }
            };
            $this->stream->on('data', $func);
        }

        $this->request->emit('file', [new File(
            $match[1], // name
            $match[2], // filename
            trim($mime[1]), // type
            $stream
        )]);

        $stream->write($content);
    }

    /**
     * Parse POST values
     *
     * @param $string
     * @return array
     */
    protected function post($string)
    {
        preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $string, $match);

        $this->request->emit('post', [$match[1], $match[2]]);
    }
}
