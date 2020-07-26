<?php

namespace React\Http\Io;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RingCentral\Psr7\Request;
use RingCentral\Psr7\Response;
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
}
