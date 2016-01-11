<?php

namespace React\Http;

/**
 * Parse a multipart body
 *
 * Original source is from https://gist.github.com/jas-/5c3fdc26fedd11cb9fb5
 *
 * @author jason.gerfen@gmail.com
 * @author stephane.goetz@onigoetz.ch
 * @license http://www.gnu.org/licenses/gpl.html GPL License 3
 */
class MultipartParser
{
    /**
     * @var string
     */
    protected $input;

    /**
     * @var string
     */
    protected $boundary;

    /**
     * Contains the resolved posts
     *
     * @var array
     */
    protected $post = [];

    /**
     * Contains the resolved files
     *
     * @var array
     */
    protected $files = [];

    /**
     * @param $input
     * @param $boundary
     */
    public function __construct($input, $boundary)
    {
        $this->input = $input;
        $this->boundary = $boundary;
    }

    /**
     * @return array
     */
    public function getPost()
    {
        return $this->post;
    }

    /**
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Do the actual parsing
     */
    public function parse()
    {
        $blocks = $this->split($this->boundary);

        foreach ($blocks as $value) {
            if (empty($value)) {
                continue;
            }

            $this->parseBlock($value);
        }
    }

    /**
     * @param $boundary string
     * @returns Array
     */
    protected function split($boundary)
    {
        $boundary = preg_quote($boundary);
        $result = preg_split("/\\-+$boundary/", $this->input);
        array_pop($result);
        return $result;
    }

    /**
     * Decide if we handle a file, post value or octet stream
     *
     * @param $string string
     * @returns void
     */
    protected function parseBlock($string)
    {
        if (strpos($string, 'filename') !== false) {
            $this->file($string);
            return;
        }

        // This may never be called, if an octet stream
        // has a filename it is catched by the previous
        // condition already.
        if (strpos($string, 'application/octet-stream') !== false) {
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

        $this->addResolved('post', $match[1], $match[2]);
    }

    /**
     * Parse a file
     *
     * @param $string
     * @return array
     */
    protected function file($string)
    {
        $match = array();
        preg_match('/name=\"(?P<name>[^\"]*)\";/', $string, $match);
        $name = $match['name'];

        $match = array();
        preg_match('/filename=\"(?P<filename>[^\"]*)\"[\n|\r]/', $string, $match);
        $filename = $match['filename'];

        $match = array();
        preg_match('/Content-Type: (?P<mime>.*)?/', $string, $match);
        $mime = $match['mime'];
        //Do not use preg_match function to parse entire request body because of multiplying (twice) allocated memory for content data
        $pos = strpos($string, "\r\n\r\n");
        $string = substr_replace($string, '', 0, $pos);
        // Put content in a stream
        $stream = fopen('php://temp', 'r+');
        if ($string !== '') {
            fwrite($stream, $string);
            fseek($stream, 0);
        }
        $data = [
            'name' => $filename,
            'type' => trim($mime),
            'stream' => $stream, // Instead of writing to a file, we write to a stream.
            'error' => UPLOAD_ERR_OK,
            'size' => function_exists('mb_strlen')? mb_strlen($string, '8bit') : strlen($string),
        ];
        //TODO :: have an option to write to files to emulate the same functionality as a real php server
        //$path = tempnam(sys_get_temp_dir(), "php");
        //$err = file_put_contents($path, $content);
        //$data['tmp_name'] = $path;
        //$data['error'] = ($err === false) ? UPLOAD_ERR_NO_FILE : UPLOAD_ERR_OK;

        $this->addResolved('files', $name, $data);
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

        $this->addResolved('post', $match[1], $match[2]);
    }

    /**
     * Put the file or post where it belongs,
     * The key names can be simple, or containing []
     * it can also be a named key
     *
     * @param $type
     * @param $key
     * @param $content
     */
    protected function addResolved($type, $key, $content)
    {
        if (preg_match('/^(.*)\[(.*)\]$/i', $key, $tmp)) {
            if (!empty($tmp[2])) {
                $this->{$type}[$tmp[1]][$tmp[2]] = $content;
            } else {
                $this->{$type}[$tmp[1]][] = $content;
            }
        } else {
            $this->{$type}[$key] = $content;
        }
    }
}

