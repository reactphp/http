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
    private $url;
    private $query;
    private $httpVersion;
    private $headers;
    private $body;
    private $post = [];

    // metadata, implicitly added externally
    public $remoteAddress;

    public function __construct($method, $url, $query = array(), $httpVersion = '1.1', $headers = array(), $body = '')
    {
        $this->method = $method;
        $this->url = $url;
        $this->query = $query;
        $this->httpVersion = $httpVersion;
        $this->headers = $headers;
        $this->body = $body;

        $this->on('post', function ($key, $value) {
            $this->addPost($key, $value);
        });
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getPath()
    {
        return $this->url->getPath();
    }

    public function getUrl()
    {
        return $this->url;
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

    public function getBody()
    {
        return $this->body;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function appendBody($data)
    {
        $this->body .= $data;
    }

    public function getPost()
    {
        return $this->post;
    }

    public function setPost($post)
    {
        $this->post = $post;
    }

    public function getRemoteAddress()
    {
        return $this->remoteAddress;
    }

    public function expectsContinue()
    {
        return isset($this->headers['Expect']) && '100-continue' === $this->headers['Expect'];
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

    /**
     * Put the file or post where it belongs,
     * The key names can be simple, or containing []
     * it can also be a named key
     *
     * @param $key
     * @param $content
     */
    protected function addPost($key, $content)
    {
        if (preg_match('/^(.*)\[(.*)\]$/i', $key, $tmp)) {
            if (!empty($tmp[2])) {
                $this->post[$tmp[1]][$tmp[2]] = $content;
            } else {
                $this->post[$tmp[1]][] = $content;
            }
        } else {
            $this->post[$key] = $content;
        }
    }
}
