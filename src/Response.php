<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Socket\ConnectionInterface;
use React\Stream\WritableStreamInterface;

class Response extends EventEmitter implements WritableStreamInterface
{
    private $closed = false;
    private $writable = true;
    private $conn;
    private $headWritten = false;
    private $chunkedEncoding = true;

    public function __construct(ConnectionInterface $conn)
    {
        $this->conn = $conn;
        $that = $this;
        $this->conn->on('end', function () use ($that) {
            $that->close();
        });

        $this->conn->on('error', function ($error) use ($that) {
            $that->emit('error', array($error, $that));
            $that->close();
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
     * Note that calling this method is strictly optional.
     * If you do not use it, then the client MUST continue sending the request body
     * after waiting some time.
     *
     * This method MUST NOT be invoked after calling `writeHead()`.
     * Calling this method after sending the headers will result in an `Exception`.
     *
     * @return void
     * @throws \Exception
     * @see Request::expectsContinue()
     */
    public function writeContinue()
    {
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
     * Calling this method more than once will result in an `Exception`.
     *
     * Unless you specify a `Content-Length` header yourself, the response message
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
     * @param int   $status
     * @param array $headers
     * @throws \Exception
     */
    public function writeHead($status = 200, array $headers = array())
    {
        if ($this->headWritten) {
            throw new \Exception('Response head has already been written.');
        }

        $lower = array_change_key_case($headers);

        // disable chunked encoding if content-length is given
        if (isset($lower['content-length'])) {
            $this->chunkedEncoding = false;
        }

        // assign default "X-Powered-By" header as first for history reasons
        if (!isset($lower['x-powered-by'])) {
            $headers = array_merge(
                array('X-Powered-By' => 'React/alpha'),
                $headers
            );
        }

        // assign chunked transfer-encoding if chunked encoding is used
        if ($this->chunkedEncoding) {
            foreach($headers as $name => $value) {
                if (strtolower($name) === 'transfer-encoding') {
                    unset($headers[$name]);
                }
            }

            $headers['Transfer-Encoding'] = 'chunked';
        }

        $data = $this->formatHead($status, $headers);
        $this->conn->write($data);

        $this->headWritten = true;
    }

    private function formatHead($status, array $headers)
    {
        $status = (int) $status;
        $text = isset(ResponseCodes::$statusTexts[$status]) ? ResponseCodes::$statusTexts[$status] : '';
        $data = "HTTP/1.1 $status $text\r\n";

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
        if (null !== $data) {
            $this->write($data);
        }

        if ($this->chunkedEncoding) {
            $this->conn->write("0\r\n\r\n");
        }

        $this->emit('end');
        $this->removeAllListeners();
        $this->conn->end();
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        $this->writable = false;
        $this->emit('close');
        $this->removeAllListeners();
        $this->conn->close();
    }
}
