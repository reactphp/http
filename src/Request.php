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
    private $query;
    private $httpVersion;
    private $headers;

    // metadata, implicitly added externally
    public $remoteAddress;

    public function __construct($method, $path, $query = array(), $httpVersion = '1.1', $headers = array())
    {
        $this->method = $method;
        $this->path = $path;
        $this->query = $query;
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

    /**
     * Returns ALL headers
     *
     * This will return an (possibly empty) assoc array with header names as
     * key and header values as value. The header value will be a string if
     * there's only a single value or an array of strings if this header has
     * multiple values.
     *
     * Note that this differs from the PSR-7 implementation of this method.
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * @param string $name
     * @return string[] a list of all values for this header name or an empty array if header was not found
     */
    public function getHeader($name)
    {
        $found = array();

        $name = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $name) {
                foreach((array)$value as $one) {
                    $found []= $one;
                }
            }
        }

        return $found;
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * @param string $name
     * @return string a comma-separated list of all values for this header name or an empty string if header was not found
     */
    public function getHeaderLine($name)
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name
     * @return bool
     */
    public function hasHeader($name)
    {
        return !!$this->getHeader($name);
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
}
