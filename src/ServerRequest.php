<?php

namespace React\Http;

use Psr\Http\Message\ServerRequestInterface;
use RingCentral\Psr7\Request;

/** @internal */
class ServerRequest extends Request implements ServerRequestInterface
{
    private $attributes = array();

    private $serverParams = array();
    private $fileParams = array();
    private $cookies = array();
    private $queryParams = array();
    private $parsedBody = null;

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
        if (!array_key_exists($name, $this->attributes)) {
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

    /** @internal
     * Used only internal set the retrospective server params
     **/
    public function withServerParams(array $serverParams)
    {
        $new = clone $this;
        $new->serverParams= $serverParams;
        return $new;
    }

    /**
     * @internal
     * @param string $cookie
     * @return boolean|mixed[]
     */
    public static function parseCookie($cookie)
    {
        // PSR-7 `getHeadline('Cookies')` will return multiple
        // cookie header coma-seperated. Multiple cookie headers
        // are not allowed according to https://tools.ietf.org/html/rfc6265#section-5.4
        if (strpos($cookie, ',') !== false) {
            return false;
        }

        $cookieArray = explode(';', $cookie);

        $result = array();
        foreach ($cookieArray as $pair) {
            $nameValuePair = explode('=', $pair, 2);
            if (count($nameValuePair) === 2) {
                $key = urldecode($nameValuePair[0]);
                $value = urldecode($nameValuePair[1]);

                $result[$key] = $value;
            }
        }

        return $result;
    }
}
