<?php

namespace React\Http;

use Evenement\EventEmitter;
use Psr\Http\Message\UriInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

/**
 * @event pause
 * @event resume
 * @event end
 */
class Request extends EventEmitter implements ReadableStreamInterface
{
    /**
     * @var bool
     */
    private $readable = true;

    /**
     * @var string
     */
    private $method;

    /**
     * @var UriInterface|null
     */
    private $url;

    /**
     * @var array
     */
    private $query;

    /**
     * @var string
     */
    private $httpVersion;

    /**
     * @var array
     */
    private $headers;

    /**
     * @var string
     */
    private $body;

    /**
     * @var array
     */
    private $post = [];

    /**
     * @var array
     */
    private $files = [];

    // metadata, implicitly added externally
    public $remoteAddress;

    /**
     * @param string $method
     * @param        $url
     * @param array  $query
     * @param string $httpVersion
     * @param array  $headers
     * @param string $body
     */
    public function __construct($method, $url, $query = array(), $httpVersion = '1.1', $headers = array(), $body = '')
    {
        $this->method = $method;
        $this->url = $url;
        $this->query = $query;
        $this->httpVersion = $httpVersion;
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->url->getPath();
    }

    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return array
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @return string
     */
    public function getHttpVersion()
    {
        return $this->httpVersion;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param $body
     */
    public function setBody($body)
    {
        $this->body = $body;
    }

    /**
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param $files
     */
    public function setFiles($files)
    {
        $this->files = $files;
    }

    /**
     * @return array
     */
    public function getPost()
    {
        return $this->post;
    }

    /**
     * @param $post
     */
    public function setPost($post)
    {
        $this->post = $post;
    }

    /**
     * @return mixed
     */
    public function getRemoteAddress()
    {
        return $this->remoteAddress;
    }

    /**
     * @return bool
     */
    public function expectsContinue()
    {
        return isset($this->headers['Expect']) && '100-continue' === $this->headers['Expect'];
    }

    /**
     * @return bool
     */
    public function isReadable()
    {
        return $this->readable;
    }

    /**
     * @return null
     */
    public function pause()
    {
        $this->emit('pause');
    }

    /**
     * @return null
     */
    public function resume()
    {
        $this->emit('resume');
    }

    /**
     * @return null
     */
    public function close()
    {
        $this->readable = false;
        $this->emit('end');
        $this->removeAllListeners();
    }

    /**
     * @param WritableStreamInterface $dest
     * @param array                   $options
     * @return WritableStreamInterface
     */
    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }
}
