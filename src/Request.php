<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

class Request extends EventEmitter implements ReadableStreamInterface
{
    private $readable = true;
    private $method;
    private $path;
    private $post;
    private $files;
    private $query;
    private $httpVersion;
    private $headers;

    // metadata, implicitly added externally
    public $remoteAddress;

    public function __construct($method, $path, $query = array(), $post = array(), $files = array(), $httpVersion = '1.1', $headers = array())
    {
        $this->method = $method;
        $this->path = $path;
        $this->query = $query;
        $this->post = $post;
        $this->files = $files;
        $this->httpVersion = $httpVersion;
        $this->headers = $headers;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getHttpVersion()
    {
        return $this->httpVersion;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getHeader($name)
    {
        if(array_key_exists($name, $this->headers)) {
            return $this->headers[$name];
        }
        return null;
    }

    public function setPost($post)
    {
        $this->post = $post;
    }

    public function addPost($key, $value)
    {
        $this->post[$key] = $value;
    }

    public function getPost()
    {
        return $this->post;
    }

    public function addFile($key, $name, $temp_name, $error, $size)
    {
        $this->files[$key] = [
            'filename'          => $name,
            'tmp_name'          => $temp_name,
            'error'             => $error,
            'size'              => $size
        ];
    }

    public function setFiles($files)
    {
        $this->files = $files;
    }

    public function getFiles()
    {
        return $this->files;
    }

    public function getContentType()
    {
        return $this->getHeader('Content-Type');
    }

    public function expectsContinue()
    {
        return isset($this->headers['Expect']) && '100-continue' === $this->headers['Expect'];
    }

    public function isMultiPart() 
    {
        $type = $this->getContentType();
        if ($type == null || 0 !== strpos($type, "multipart/form-data"))
            return false;
        return true;
    }

    public function isReadable()
    {
        return $this->readable;
    }

    public function pause()
    {
        $this->emit('pause');
    }

    public function resume()
    {
        $this->emit('resume');
    }

    public function close()
    {
        $this->readable = false;
        $this->emit('end');
        $this->removeAllListeners();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }
}
