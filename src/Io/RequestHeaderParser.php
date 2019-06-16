<?php

namespace React\Http\Io;

use Evenement\EventEmitter;
use Psr\Http\Message\ServerRequestInterface;
use React\Socket\ConnectionInterface;
use RingCentral\Psr7 as g7;
use Exception;

/**
 * [Internal] Parses an incoming request header from an input stream
 *
 * This is used internally to parse the request header from the connection and
 * then process the remaining connection as the request body.
 *
 * @event headers
 * @event error
 *
 * @internal
 */
class RequestHeaderParser extends EventEmitter
{
    private $maxSize = 8192;

    public function handle(ConnectionInterface $conn)
    {
        $buffer = '';
        $maxSize = $this->maxSize;
        $that = $this;
        $conn->on('data', $fn = function ($data) use (&$buffer, &$fn, $conn, $maxSize, $that) {
            // append chunk of data to buffer and look for end of request headers
            $buffer .= $data;
            $endOfHeader = \strpos($buffer, "\r\n\r\n");

            // reject request if buffer size is exceeded
            if ($endOfHeader > $maxSize || ($endOfHeader === false && isset($buffer[$maxSize]))) {
                $conn->removeListener('data', $fn);
                $fn = null;

                $that->emit('error', array(
                    new \OverflowException("Maximum header size of {$maxSize} exceeded.", 431),
                    $conn
                ));
                return;
            }

            // ignore incomplete requests
            if ($endOfHeader === false) {
                return;
            }

            // request headers received => try to parse request
            $conn->removeListener('data', $fn);
            $fn = null;

            try {
                $request = $that->parseRequest(
                    (string)\substr($buffer, 0, $endOfHeader),
                    $conn->getRemoteAddress(),
                    $conn->getLocalAddress()
                );
            } catch (Exception $exception) {
                $buffer = '';
                $that->emit('error', array(
                    $exception,
                    $conn
                ));
                return;
            }

            $contentLength = 0;
            $stream = new CloseProtectionStream($conn);
            if ($request->hasHeader('Transfer-Encoding')) {
                $contentLength = null;
                $stream = new ChunkedDecoder($stream);
            } elseif ($request->hasHeader('Content-Length')) {
                $contentLength = (int)$request->getHeaderLine('Content-Length');
            }

            if ($contentLength !== null) {
                $stream = new LengthLimitedStream($stream, $contentLength);
            }

            $request = $request->withBody(new HttpBodyStream($stream, $contentLength));

            $bodyBuffer = isset($buffer[$endOfHeader + 4]) ? \substr($buffer, $endOfHeader + 4) : '';
            $buffer = '';
            $that->emit('headers', array($request, $conn));

            if ($bodyBuffer !== '') {
                $conn->emit('data', array($bodyBuffer));
            }

            // happy path: request body is known to be empty => immediately end stream
            if ($contentLength === 0) {
                $stream->emit('end');
                $stream->close();
            }
        });

        $conn->on('close', function () use (&$buffer, &$fn) {
            $fn = $buffer = null;
        });
    }

    /**
     * @param string $headers buffer string containing request headers only
     * @param ?string $remoteSocketUri
     * @param ?string $localSocketUri
     * @return ServerRequestInterface
     * @throws \InvalidArgumentException
     * @internal
     */
    public function parseRequest($headers, $remoteSocketUri, $localSocketUri)
    {
        // additional, stricter safe-guard for request line
        // because request parser doesn't properly cope with invalid ones
        if (!\preg_match('#^[^ ]+ [^ ]+ HTTP/\d\.\d#m', $headers)) {
            throw new \InvalidArgumentException('Unable to parse invalid request-line');
        }

        // parser does not support asterisk-form and authority-form
        // remember original target and temporarily replace and re-apply below
        $originalTarget = null;
        if (\strncmp($headers, 'OPTIONS * ', 10) === 0) {
            $originalTarget = '*';
            $headers = 'OPTIONS / ' . \substr($headers, 10);
        } elseif (\strncmp($headers, 'CONNECT ', 8) === 0) {
            $parts = \explode(' ', $headers, 3);
            $uri = \parse_url('tcp://' . $parts[1]);

            // check this is a valid authority-form request-target (host:port)
            if (isset($uri['scheme'], $uri['host'], $uri['port']) && count($uri) === 3) {
                $originalTarget = $parts[1];
                $parts[1] = 'http://' . $parts[1] . '/';
                $headers = implode(' ', $parts);
            } else {
                throw new \InvalidArgumentException('CONNECT method MUST use authority-form request target');
            }
        }

        // parse request headers into obj implementing RequestInterface
        $request = g7\parse_request($headers);

        // create new obj implementing ServerRequestInterface by preserving all
        // previous properties and restoring original request-target
        $serverParams = array(
            'REQUEST_TIME' => \time(),
            'REQUEST_TIME_FLOAT' => \microtime(true)
        );

        // apply REMOTE_ADDR and REMOTE_PORT if source address is known
        // address should always be known, unless this is over Unix domain sockets (UDS)
        if ($remoteSocketUri !== null) {
            $remoteAddress = \parse_url($remoteSocketUri);
            $serverParams['REMOTE_ADDR'] = $remoteAddress['host'];
            $serverParams['REMOTE_PORT'] = $remoteAddress['port'];
        }

        // apply SERVER_ADDR and SERVER_PORT if server address is known
        // address should always be known, even for Unix domain sockets (UDS)
        // but skip UDS as it doesn't have a concept of host/port.s
        if ($localSocketUri !== null) {
            $localAddress = \parse_url($localSocketUri);
            if (isset($localAddress['host'], $localAddress['port'])) {
                $serverParams['SERVER_ADDR'] = $localAddress['host'];
                $serverParams['SERVER_PORT'] = $localAddress['port'];
            }
            if (isset($localAddress['scheme']) && $localAddress['scheme'] === 'tls') {
                $serverParams['HTTPS'] = 'on';
            }
        }

        $target = $request->getRequestTarget();
        $request = new ServerRequest(
            $request->getMethod(),
            $request->getUri(),
            $request->getHeaders(),
            $request->getBody(),
            $request->getProtocolVersion(),
            $serverParams
        );
        $request = $request->withRequestTarget($target);

        // re-apply actual request target from above
        if ($originalTarget !== null) {
            $request = $request->withUri(
                $request->getUri()->withPath(''),
                true
            )->withRequestTarget($originalTarget);
        }

        // only support HTTP/1.1 and HTTP/1.0 requests
        $protocolVersion = $request->getProtocolVersion();
        if ($protocolVersion !== '1.1' && $protocolVersion !== '1.0') {
            throw new \InvalidArgumentException('Received request with invalid protocol version', 505);
        }

        // ensure absolute-form request-target contains a valid URI
        $requestTarget = $request->getRequestTarget();
        if (\strpos($requestTarget, '://') !== false && \substr($requestTarget, 0, 1) !== '/') {
            $parts = \parse_url($requestTarget);

            // make sure value contains valid host component (IP or hostname), but no fragment
            if (!isset($parts['scheme'], $parts['host']) || $parts['scheme'] !== 'http' || isset($parts['fragment'])) {
                throw new \InvalidArgumentException('Invalid absolute-form request-target');
            }
        }

        // Optional Host header value MUST be valid (host and optional port)
        if ($request->hasHeader('Host')) {
            $parts = \parse_url('http://' . $request->getHeaderLine('Host'));

            // make sure value contains valid host component (IP or hostname)
            if (!$parts || !isset($parts['scheme'], $parts['host'])) {
                $parts = false;
            }

            // make sure value does not contain any other URI component
            unset($parts['scheme'], $parts['host'], $parts['port']);
            if ($parts === false || $parts) {
                throw new \InvalidArgumentException('Invalid Host header value');
            }
        }

        // ensure message boundaries are valid according to Content-Length and Transfer-Encoding request headers
        if ($request->hasHeader('Transfer-Encoding')) {
            if (\strtolower($request->getHeaderLine('Transfer-Encoding')) !== 'chunked') {
                throw new \InvalidArgumentException('Only chunked-encoding is allowed for Transfer-Encoding', 501);
            }

            // Transfer-Encoding: chunked and Content-Length header MUST NOT be used at the same time
            // as per https://tools.ietf.org/html/rfc7230#section-3.3.3
            if ($request->hasHeader('Content-Length')) {
                throw new \InvalidArgumentException('Using both `Transfer-Encoding: chunked` and `Content-Length` is not allowed', 400);
            }
        } elseif ($request->hasHeader('Content-Length')) {
            $string = $request->getHeaderLine('Content-Length');

            if ((string)(int)$string !== $string) {
                // Content-Length value is not an integer or not a single integer
                throw new \InvalidArgumentException('The value of `Content-Length` is not valid', 400);
            }
        }

        // set URI components from socket address if not already filled via Host header
        if ($request->getUri()->getHost() === '') {
            $parts = \parse_url($localSocketUri);
            if (!isset($parts['host'], $parts['port'])) {
                $parts = array('host' => '127.0.0.1', 'port' => 80);
            }

            $request = $request->withUri(
                $request->getUri()->withScheme('http')->withHost($parts['host'])->withPort($parts['port']),
                true
            );
        }

        // Do not assume this is HTTPS when this happens to be port 443
        // detecting HTTPS is left up to the socket layer (TLS detection)
        if ($request->getUri()->getScheme() === 'https') {
            $request = $request->withUri(
                $request->getUri()->withScheme('http')->withPort(443),
                true
            );
        }

        // Update request URI to "https" scheme if the connection is encrypted
        $parts = \parse_url($localSocketUri);
        if (isset($parts['scheme']) && $parts['scheme'] === 'tls') {
            // The request URI may omit default ports here, so try to parse port
            // from Host header field (if possible)
            $port = $request->getUri()->getPort();
            if ($port === null) {
                $port = \parse_url('tcp://' . $request->getHeaderLine('Host'), PHP_URL_PORT); // @codeCoverageIgnore
            }

            $request = $request->withUri(
                $request->getUri()->withScheme('https')->withPort($port),
                true
            );
        }

        // always sanitize Host header because it contains critical routing information
        $request = $request->withUri($request->getUri()->withUserInfo('u')->withUserInfo(''));

        return $request;
    }
}
