<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;
use Psr\Http\Message\RequestInterface;

/**
 * The `Request` class is responsible for streaming the incoming request body
 * and contains meta data which was parsed from the request headers.
 *
 * It implements the `ReadableStreamInterface`.
 *
 * The constructor is internal, you SHOULD NOT call this yourself.
 * The `Server` is responsible for emitting `Request` and `Response` objects.
 *
 * See the usage examples and the class outline for details.
 *
 * @see ReadableStreamInterface
 * @see Server
 */
class Request extends EventEmitter implements ReadableStreamInterface
{
    private $readable = true;
    private $request;
    private $stream;

    // metadata, implicitly added externally
    public $remoteAddress;

    /**
     * The constructor is internal, you SHOULD NOT call this yourself.
     *
     * The `Server` is responsible for emitting `Request` and `Response` objects.
     *
     * Constructor parameters may change at any time.
     *
     * @internal
     */
    public function __construct(RequestInterface $request, ReadableStreamInterface $stream)
    {
        $this->request = $request;
        $this->stream = $stream;

        $that = $this;
        // forward data and end events from body stream to request
        $stream->on('data', function ($data) use ($that) {
            $that->emit('data', array($data));
        });
        $stream->on('end', function () use ($that) {
            $that->emit('end');
        });
        $stream->on('error', function ($error) use ($that) {
            $that->emit('error', array($error));
        });
        $stream->on('close', array($this, 'close'));
    }

    /**
     * Returns the request method
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->request->getMethod();
    }

    /**
     * Returns the request path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->request->getUri()->getPath();
    }

    /**
     * Returns an array with all query parameters ($_GET)
     *
     * @return array
     */
    public function getQueryParams()
    {
        $params = array();
        parse_str($this->request->getUri()->getQuery(), $params);

        return $params;
    }

    /**
     * Returns the HTTP protocol version (such as "1.0" or "1.1")
     *
     * @return string
     */
    public function getProtocolVersion()
    {
        return $this->request->getProtocolVersion();
    }

    /**
     * Returns an array with ALL headers
     *
     * The keys represent the header name in the exact case in which they were
     * originally specified. The values will be an array of strings for each
     * value for the respective header name.
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->request->getHeaders();
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * @param string $name
     * @return string[] a list of all values for this header name or an empty array if header was not found
     */
    public function getHeader($name)
    {
        return $this->request->getHeader($name);
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * @param string $name
     * @return string a comma-separated list of all values for this header name or an empty string if header was not found
     */
    public function getHeaderLine($name)
    {
        return $this->request->getHeaderLine($name);
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name
     * @return bool
     */
    public function hasHeader($name)
    {
        return $this->request->hasHeader($name);
    }

    /**
     * Checks if the request headers contain the `Expect: 100-continue` header.
     *
     * This header MAY be included when an HTTP/1.1 client wants to send a bigger
     * request body.
     * See [`writeContinue()`] for more details.
     *
     * This will always be `false` for HTTP/1.0 requests, regardless of what
     * any header values say.
     *
     * @return bool
     * @see Response::writeContinue()
     */
    public function expectsContinue()
    {
        return $this->getProtocolVersion() !== '1.0' && '100-continue' === strtolower($this->getHeaderLine('Expect'));
    }

    public function isReadable()
    {
        return $this->readable;
    }

    public function pause()
    {
        if (!$this->readable) {
            return;
        }

        $this->stream->pause();
    }

    public function resume()
    {
        if (!$this->readable) {
            return;
        }

        $this->stream->resume();
    }

    public function close()
    {
        if (!$this->readable) {
            return;
        }

        $this->readable = false;
        $this->stream->close();

        $this->emit('close');
        $this->removeAllListeners();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }
}
