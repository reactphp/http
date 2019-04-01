<?php

namespace React\Http\Io;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RingCentral\Psr7\Request;

/**
 * [Internal] Implementation of the PSR-7 `ServerRequestInterface`
 *
 * This is used internally to represent each incoming request message.
 *
 * This implementation builds on top of an existing outgoing request message and
 * only adds required server methods.
 *
 * Note that this is an internal class only and nothing you should usually care
 * about. See the `ServerRequestInterface` for more details.
 *
 * @see ServerRequestInterface
 * @internal
 */
class ServerRequest extends Request implements ServerRequestInterface
{
    private $attributes = array();

    private $serverParams;
    private $fileParams = array();
    private $cookies = array();
    private $queryParams = array();
    private $parsedBody;

    /**
     * @param null|string $method HTTP method for the request.
     * @param null|string|UriInterface $uri URI for the request.
     * @param array $headers Headers for the message.
     * @param string|resource|StreamInterface $body Message body.
     * @param string $protocolVersion HTTP protocol version.
     * @param array $serverParams server-side parameters
     *
     * @throws \InvalidArgumentException for an invalid URI
     */
    public function __construct(
        $method,
        $uri,
        array $headers = array(),
        $body = null,
        $protocolVersion = '1.1',
        $serverParams = array()
    ) {
        $this->serverParams = $serverParams;
        parent::__construct($method, $uri, $headers, $body, $protocolVersion);

        $query = $this->getUri()->getQuery();
        if ($query !== '') {
            \parse_str($query, $this->queryParams);
        }

        $this->cookies = $this->parseCookie($this->getHeaderLine('Cookie'));
    }

    public function getServerParams()
    {
        return $this->serverParams;
    }

    public function getCookieParams()
    {
        return $this->cookies;
    }

    public function withCookieParams(array $cookies)
    {
        $new = clone $this;
        $new->cookies = $cookies;
        return $new;
    }

    public function getQueryParams()
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query)
    {
        $new = clone $this;
        $new->queryParams = $query;
        return $new;
    }

    public function getUploadedFiles()
    {
        return $this->fileParams;
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        $new = clone $this;
        $new->fileParams = $uploadedFiles;
        return $new;
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data)
    {
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        if (!\array_key_exists($name, $this->attributes)) {
            return $default;
        }
        return $this->attributes[$name];
    }

    public function withAttribute($name, $value)
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    public function withoutAttribute($name)
    {
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }

    /**
     * @param string $cookie
     * @return array
     */
    private function parseCookie($cookie)
    {
        // PSR-7 `getHeaderLine('Cookie')` will return multiple
        // cookie header comma-seperated. Multiple cookie headers
        // are not allowed according to https://tools.ietf.org/html/rfc6265#section-5.4
        if ($cookie === '' || \strpos($cookie, ',') !== false) {
            return array();
        }

        $cookieArray = \explode(';', $cookie);
        $result = array();

        foreach ($cookieArray as $pair) {
            $pair = \trim($pair);
            $nameValuePair = \explode('=', $pair, 2);

            if (\count($nameValuePair) === 2) {
                $key = \urldecode($nameValuePair[0]);
                $value = \urldecode($nameValuePair[1]);
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
