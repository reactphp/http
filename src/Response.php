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

    public function writeContinue()
    {
        if ($this->headWritten) {
            throw new \Exception('Response head has already been written.');
        }

        $this->conn->write("HTTP/1.1 100 Continue\r\n\r\n");
    }

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

        if ($this->chunkedEncoding) {
            $len = strlen($data);
            $chunk = dechex($len)."\r\n".$data."\r\n";
            $flushed = $this->conn->write($chunk);
        } else {
            $flushed = $this->conn->write($data);
        }

        return $flushed;
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
