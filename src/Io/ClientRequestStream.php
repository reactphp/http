<?php

namespace React\Http\Io;

use Evenement\EventEmitter;
use Psr\Http\Message\RequestInterface;
use React\Http\Message\Response;
use React\Http\Psr7\Utils;
use React\Promise;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use React\Stream\WritableStreamInterface;

/**
 * @event response
 * @event drain
 * @event error
 * @event close
 * @internal
 */
class ClientRequestStream extends EventEmitter implements WritableStreamInterface
{
    const STATE_INIT = 0;
    const STATE_WRITING_HEAD = 1;
    const STATE_HEAD_WRITTEN = 2;
    const STATE_END = 3;

    /** @var ConnectorInterface */
    private $connector;

    /** @var RequestInterface */
    private $request;

    /** @var ?ConnectionInterface */
    private $connection;

    /** @var string */
    private $buffer = '';

    private $responseFactory;
    private $state = self::STATE_INIT;
    private $ended = false;

    private $pendingWrites = '';

    public function __construct(ConnectorInterface $connector, RequestInterface $request)
    {
        $this->connector = $connector;
        $this->request = $request;
    }

    public function isWritable()
    {
        return self::STATE_END > $this->state && !$this->ended;
    }

    private function writeHead()
    {
        $this->state = self::STATE_WRITING_HEAD;

        $request = $this->request;
        $connectionRef = &$this->connection;
        $stateRef = &$this->state;
        $pendingWrites = &$this->pendingWrites;
        $that = $this;

        $promise = $this->connect();
        $promise->then(
            function (ConnectionInterface $connection) use ($request, &$connectionRef, &$stateRef, &$pendingWrites, $that) {
                $connectionRef = $connection;
                assert($connectionRef instanceof ConnectionInterface);

                $connection->on('drain', array($that, 'handleDrain'));
                $connection->on('data', array($that, 'handleData'));
                $connection->on('end', array($that, 'handleEnd'));
                $connection->on('error', array($that, 'handleError'));
                $connection->on('close', array($that, 'close'));

                assert($request instanceof RequestInterface);
                $headers = "{$request->getMethod()} {$request->getRequestTarget()} HTTP/{$request->getProtocolVersion()}\r\n";
                foreach ($request->getHeaders() as $name => $values) {
                    foreach ($values as $value) {
                        $headers .= "$name: $value\r\n";
                    }
                }

                $more = $connection->write($headers . "\r\n" . $pendingWrites);

                assert($stateRef === ClientRequestStream::STATE_WRITING_HEAD);
                $stateRef = ClientRequestStream::STATE_HEAD_WRITTEN;

                // clear pending writes if non-empty
                if ($pendingWrites !== '') {
                    $pendingWrites = '';

                    if ($more) {
                        $that->emit('drain');
                    }
                }
            },
            array($this, 'closeError')
        );

        $this->on('close', function() use ($promise) {
            $promise->cancel();
        });
    }

    public function write($data)
    {
        if (!$this->isWritable()) {
            return false;
        }

        // write directly to connection stream if already available
        if (self::STATE_HEAD_WRITTEN <= $this->state) {
            return $this->connection->write($data);
        }

        // otherwise buffer and try to establish connection
        $this->pendingWrites .= $data;
        if (self::STATE_WRITING_HEAD > $this->state) {
            $this->writeHead();
        }

        return false;
    }

    public function end($data = null)
    {
        if (!$this->isWritable()) {
            return;
        }

        if (null !== $data) {
            $this->write($data);
        } else if (self::STATE_WRITING_HEAD > $this->state) {
            $this->writeHead();
        }

        $this->ended = true;
    }

    /** @internal */
    public function handleDrain()
    {
        $this->emit('drain');
    }

    /** @internal */
    public function handleData($data)
    {
        $this->buffer .= $data;

        // buffer until double CRLF (or double LF for compatibility with legacy servers)
        if (false !== strpos($this->buffer, "\r\n\r\n") || false !== strpos($this->buffer, "\n\n")) {
            try {
                $response = Utils::parseResponse($this->buffer);
                $bodyChunk = (string) $response->getBody();
            } catch (\InvalidArgumentException $exception) {
                $this->closeError($exception);
                return;
            }

            // response headers successfully received => remove listeners for connection events
            $connection = $this->connection;
            assert($connection instanceof ConnectionInterface);
            $connection->removeListener('drain', array($this, 'handleDrain'));
            $connection->removeListener('data', array($this, 'handleData'));
            $connection->removeListener('end', array($this, 'handleEnd'));
            $connection->removeListener('error', array($this, 'handleError'));
            $connection->removeListener('close', array($this, 'close'));
            $this->connection = null;
            $this->buffer = '';

            // take control over connection handling and close connection once response body closes
            $that = $this;
            $input = $body = new CloseProtectionStream($connection);
            $input->on('close', function () use ($connection, $that) {
                $connection->close();
                $that->close();
            });

            // determine length of response body
            $length = null;
            $code = $response->getStatusCode();
            if ($this->request->getMethod() === 'HEAD' || ($code >= 100 && $code < 200) || $code == Response::STATUS_NO_CONTENT || $code == Response::STATUS_NOT_MODIFIED) {
                $length = 0;
            } elseif (\strtolower($response->getHeaderLine('Transfer-Encoding')) === 'chunked') {
                $body = new ChunkedDecoder($body);
            } elseif ($response->hasHeader('Content-Length')) {
                $length = (int) $response->getHeaderLine('Content-Length');
            }
            $response = $response->withBody($body = new ReadableBodyStream($body, $length));

            // emit response with streaming response body (see `Sender`)
            $this->emit('response', array($response, $body));

            // re-emit HTTP response body to trigger body parsing if parts of it are buffered
            if ($bodyChunk !== '') {
                $input->handleData($bodyChunk);
            } elseif ($length === 0) {
                $input->handleEnd();
            }
        }
    }

    /** @internal */
    public function handleEnd()
    {
        $this->closeError(new \RuntimeException(
            "Connection ended before receiving response"
        ));
    }

    /** @internal */
    public function handleError(\Exception $error)
    {
        $this->closeError(new \RuntimeException(
            "An error occurred in the underlying stream",
            0,
            $error
        ));
    }

    /** @internal */
    public function closeError(\Exception $error)
    {
        if (self::STATE_END <= $this->state) {
            return;
        }
        $this->emit('error', array($error));
        $this->close();
    }

    public function close()
    {
        if (self::STATE_END <= $this->state) {
            return;
        }

        $this->state = self::STATE_END;
        $this->pendingWrites = '';
        $this->buffer = '';

        if ($this->connection instanceof ConnectionInterface) {
            $this->connection->close();
            $this->connection = null;
        }

        $this->emit('close');
        $this->removeAllListeners();
    }

    protected function connect()
    {
        $scheme = $this->request->getUri()->getScheme();
        if ($scheme !== 'https' && $scheme !== 'http') {
            return Promise\reject(
                new \InvalidArgumentException('Invalid request URL given')
            );
        }

        $host = $this->request->getUri()->getHost();
        $port = $this->request->getUri()->getPort();

        if ($scheme === 'https') {
            $host = 'tls://' . $host;
        }

        if ($port === null) {
            $port = $scheme === 'https' ? 443 : 80;
        }

        return $this->connector
            ->connect($host . ':' . $port);
    }
}
