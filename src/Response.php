<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Stream\WritableStreamInterface;

/**
 * The `Response` class is responsible for streaming the outgoing response body.
 *
 * It implements the `WritableStreamInterface`.
 *
 * The constructor is internal, you SHOULD NOT call this yourself.
 * The `Server` is responsible for emitting `Request` and `Response` objects.
 *
 * The `Response` will automatically use the same HTTP protocol version as the
 * corresponding `Request`.
 *
 * HTTP/1.1 responses will automatically apply chunked transfer encoding if
 * no `Content-Length` header has been set.
 * See `writeHead()` for more details.
 *
 * See the usage examples and the class outline for details.
 *
 * @see WritableStreamInterface
 * @see Server
 */
class Response extends EventEmitter implements WritableStreamInterface
{
    private $conn;
    private $protocolVersion;

    private $closed = false;
    private $writable = true;
    private $headWritten = false;
    private $chunkedEncoding = false;

    /**
     * The constructor is internal, you SHOULD NOT call this yourself.
     *
     * The `Server` is responsible for emitting `Request` and `Response` objects.
     *
     * Constructor parameters may change at any time.
     *
     * @internal
     */
    public function __construct(WritableStreamInterface $conn, $protocolVersion = '1.1')
    {
        $this->conn = $conn;
        $this->protocolVersion = $protocolVersion;

        $that = $this;
        $this->conn->on('close', array($this, 'close'));

        $this->conn->on('error', function ($error) use ($that) {
            $that->emit('error', array($error));
        });

        $this->conn->on('drain', function () use ($that) {
            $that->emit('drain');
        });
    }

    public function isWritable()
    {
        return $this->writable;
    }

    /**
     * Sends an intermediary `HTTP/1.1 100 continue` response.
     *
     * This is a feature that is implemented by *many* HTTP/1.1 clients.
     * When clients want to send a bigger request body, they MAY send only the request
     * headers with an additional `Expect: 100-continue` header and wait before
     * sending the actual (large) message body.
     *
     * The server side MAY use this header to verify if the request message is
     * acceptable by checking the request headers (such as `Content-Length` or HTTP
     * authentication) and then ask the client to continue with sending the message body.
     * Otherwise, the server can send a normal HTTP response message and save the
     * client from transfering the whole body at all.
     *
     * This method is mostly useful in combination with the
     * [`expectsContinue()`] method like this:
     *
     * ```php
     * $http->on('request', function (Request $request, Response $response) {
     *     if ($request->expectsContinue()) {
     *         $response->writeContinue();
     *     }
     *
     *     $response->writeHead(200, array('Content-Type' => 'text/plain'));
     *     $response->end("Hello World!\n");
     * });
     * ```
     *
     * Note that calling this method is strictly optional for HTTP/1.1 responses.
     * If you do not use it, then a HTTP/1.1 client MUST continue sending the
     * request body after waiting some time.
     *
     * This method MUST NOT be invoked after calling `writeHead()`.
     * This method MUST NOT be invoked if this is not a HTTP/1.1 response
     * (please check [`expectsContinue()`] as above).
     * Calling this method after sending the headers or if this is not a HTTP/1.1
     * response is an error that will result in an `Exception`
     * (unless the response has ended/closed already).
     * Calling this method after the response has ended/closed is a NOOP.
     *
     * @return void
     * @throws \Exception
     * @see Request::expectsContinue()
     */
    public function writeContinue()
    {
        if (!$this->writable) {
            return;
        }
        if ($this->protocolVersion !== '1.1') {
            throw new \Exception('Continue requires a HTTP/1.1 message');
        }
        if ($this->headWritten) {
            throw new \Exception('Response head has already been written.');
        }

        $this->conn->write("HTTP/1.1 100 Continue\r\n\r\n");
    }

    /**
     * Writes the given HTTP message header.
     *
     * This method MUST be invoked once before calling `write()` or `end()` to send
     * the actual HTTP message body:
     *
     * ```php
     * $response->writeHead(200, array(
     *     'Content-Type' => 'text/plain'
     * ));
     * $response->end('Hello World!');
     * ```
     *
     * Calling this method more than once will result in an `Exception`
     * (unless the response has ended/closed already).
     * Calling this method after the response has ended/closed is a NOOP.
     *
     * Unless you specify a `Content-Length` header yourself, HTTP/1.1 responses
     * will automatically use chunked transfer encoding and send the respective header
     * (`Transfer-Encoding: chunked`) automatically. If you know the length of your
     * body, you MAY specify it like this instead:
     *
     * ```php
     * $data = 'Hello World!';
     *
     * $response->writeHead(200, array(
     *     'Content-Type' => 'text/plain',
     *     'Content-Length' => strlen($data)
     * ));
     * $response->end($data);
     * ```
     *
     * Note that it will automatically assume a `X-Powered-By: react/alpha` header
     * unless your specify a custom `X-Powered-By` header yourself:
     *
     * ```php
     * $response->writeHead(200, array(
     *     'X-Powered-By' => 'PHP 3'
     * ));
     * ```
     *
     * If you do not want to send this header at all, you can use an empty array as
     * value like this:
     *
     * ```php
     * $response->writeHead(200, array(
     *     'X-Powered-By' => array()
     * ));
     * ```
     *
     * Note that persistent connections (`Connection: keep-alive`) are currently
     * not supported.
     * As such, HTTP/1.1 response messages will automatically include a
     * `Connection: close` header, irrespective of what header values are
     * passed explicitly.
     *
     * @param int   $status
     * @param array $headers
     * @throws \Exception
     */
    public function writeHead($status = 200, array $headers = array())
    {
        if (!$this->writable) {
            return;
        }
        if ($this->headWritten) {
            throw new \Exception('Response head has already been written.');
        }

        $lower = array_change_key_case($headers);

        // assign default "X-Powered-By" header as first for history reasons
        if (!isset($lower['x-powered-by'])) {
            $headers = array_merge(
                array('X-Powered-By' => 'React/alpha'),
                $headers
            );
        }

        // always remove transfer-encoding
        foreach($headers as $name => $value) {
            if (strtolower($name) === 'transfer-encoding') {
                unset($headers[$name]);
            }
        }

        // assign date header if no 'date' is given, use the current time where this code is running
        if (!isset($lower['date'])) {
            // IMF-fixdate  = day-name "," SP date1 SP time-of-day SP GMT
            $headers['Date'] = gmdate('D, d M Y H:i:s') . ' GMT';
        }

        // assign chunked transfer-encoding if no 'content-length' is given for HTTP/1.1 responses
        if (!isset($lower['content-length']) && $this->protocolVersion === '1.1') {
            $headers['Transfer-Encoding'] = 'chunked';
            $this->chunkedEncoding = true;
        }

        // HTTP/1.1 assumes persistent connection support by default
        // we do not support persistent connections, so let the client know
        if ($this->protocolVersion === '1.1') {
            foreach($headers as $name => $value) {
                if (strtolower($name) === 'connection') {
                    unset($headers[$name]);
                }
            }

            $headers['Connection'] = 'close';
        }

        $data = $this->formatHead($status, $headers);
        $this->conn->write($data);

        $this->headWritten = true;
    }

    private function formatHead($status, array $headers)
    {
        $status = (int) $status;
        $text = isset(ResponseCodes::$statusTexts[$status]) ? ResponseCodes::$statusTexts[$status] : '';
        $data = "HTTP/$this->protocolVersion $status $text\r\n";

        foreach ($headers as $name => $value) {
            $name = str_replace(array("\r", "\n"), '', $name);

            foreach ((array) $value as $val) {
                $val = str_replace(array("\r", "\n"), '', $val);

                $data .= "$name: $val\r\n";
            }
        }
        $data .= "\r\n";

        return $data;
    }

    public function write($data)
    {
        if (!$this->writable) {
            return false;
        }
        if (!$this->headWritten) {
            throw new \Exception('Response head has not yet been written.');
        }

        // prefix with chunk length for chunked transfer encoding
        if ($this->chunkedEncoding) {
            $len = strlen($data);

            // skip empty chunks
            if ($len === 0) {
                return true;
            }

            $data = dechex($len) . "\r\n" . $data . "\r\n";
        }

        return $this->conn->write($data);
    }

    public function end($data = null)
    {
        if (!$this->writable) {
            return;
        }
        if (!$this->headWritten) {
            throw new \Exception('Response head has not yet been written.');
        }

        if (null !== $data) {
            $this->write($data);
        }

        if ($this->chunkedEncoding) {
            $this->conn->write("0\r\n\r\n");
        }

        $this->writable = false;
        $this->conn->end();
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->writable = false;
        $this->conn->close();

        $this->emit('close');
        $this->removeAllListeners();
    }
}
