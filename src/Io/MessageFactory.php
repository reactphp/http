<?php

namespace React\Http\Io;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RingCentral\Psr7\Request;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\Uri;
use React\Stream\ReadableStreamInterface;

/**
 * @internal
 */
class MessageFactory
{
    /**
     * Creates a new instance of RequestInterface for the given request parameters
     *
     * @param string                         $method
     * @param string|UriInterface            $uri
     * @param array                          $headers
     * @param string|ReadableStreamInterface $content
     * @param string                         $protocolVersion
     * @return Request
     */
    public function request($method, $uri, $headers = array(), $content = '', $protocolVersion = '1.1')
    {
        return new Request($method, $uri, $headers, $this->body($content), $protocolVersion);
    }

    /**
     * Creates a new instance of ResponseInterface for the given response parameters
     *
     * @param string $protocolVersion
     * @param int    $status
     * @param string $reason
     * @param array  $headers
     * @param ReadableStreamInterface|string $body
     * @param ?string $requestMethod
     * @return Response
     * @uses self::body()
     */
    public function response($protocolVersion, $status, $reason, $headers = array(), $body = '', $requestMethod = null)
    {
        $response = new Response($status, $headers, $body instanceof ReadableStreamInterface ? null : $body, $protocolVersion, $reason);

        if ($body instanceof ReadableStreamInterface) {
            $length = null;
            $code = $response->getStatusCode();
            if ($requestMethod === 'HEAD' || ($code >= 100 && $code < 200) || $code == 204 || $code == 304) {
                $length = 0;
            } elseif (\strtolower($response->getHeaderLine('Transfer-Encoding')) === 'chunked') {
                $length = null;
            } elseif ($response->hasHeader('Content-Length')) {
                $length = (int)$response->getHeaderLine('Content-Length');
            }

            $response = $response->withBody(new ReadableBodyStream($body, $length));
        }

        return $response;
    }

    /**
     * Creates a new instance of StreamInterface for the given body contents
     *
     * @param ReadableStreamInterface|string $body
     * @return StreamInterface
     */
    public function body($body)
    {
        if ($body instanceof ReadableStreamInterface) {
            return new ReadableBodyStream($body);
        }

        return \RingCentral\Psr7\stream_for($body);
    }

    /**
     * Creates a new instance of UriInterface for the given URI string
     *
     * @param string $uri
     * @return UriInterface
     */
    public function uri($uri)
    {
        return new Uri($uri);
    }

    /**
     * Creates a new instance of UriInterface for the given URI string relative to the given base URI
     *
     * @param UriInterface $base
     * @param string       $uri
     * @return UriInterface
     */
    public function uriRelative(UriInterface $base, $uri)
    {
        return Uri::resolve($base, $uri);
    }

    /**
     * Resolves the given relative or absolute $uri by appending it behind $this base URI
     *
     * The given $uri parameter can be either a relative or absolute URI and
     * as such can not contain any URI template placeholders.
     *
     * As such, the outcome of this method represents a valid, absolute URI
     * which will be returned as an instance implementing `UriInterface`.
     *
     * If the given $uri is a relative URI, it will simply be appended behind $base URI.
     *
     * If the given $uri is an absolute URI, it will simply be returned as-is.
     *
     * @param UriInterface $uri
     * @param UriInterface $base
     * @return UriInterface
     */
    public function expandBase(UriInterface $uri, UriInterface $base)
    {
        if ($uri->getScheme() !== '') {
            return $uri;
        }

        $uri = (string)$uri;
        $base = (string)$base;

        if ($uri !== '' && substr($base, -1) !== '/' && substr($uri, 0, 1) !== '?') {
            $base .= '/';
        }

        if (isset($uri[0]) && $uri[0] === '/') {
            $uri = substr($uri, 1);
        }

        return $this->uri($base . $uri);
    }
}
