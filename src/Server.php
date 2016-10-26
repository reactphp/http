<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Socket\ServerInterface as SocketServerInterface;
use React\Socket\ConnectionInterface;


class ConnProxy {
    public $id;
    public $count;
    public $conn;
    public function __construct($id, ConnectionInterface $conn)
    {
        $this->id = $id;
        $this->conn = $conn;
        $this->count = 0;
    }
}

/** @event request */
class Server extends EventEmitter implements ServerInterface
{
    const KEEPALIVE_MAX_REQUEST = 25;
    private $connectionsCount = 0;
    public function __construct(SocketServerInterface $io)
    {
        $io->on('connection', function (ConnectionInterface $conn) {
            $this->connectionsCount += 1;
            $this->handleConnection(new ConnProxy($this->connectionsCount, $conn));
        });
    }

    public function handleConnection(ConnProxy $connProxy) {
        $conn = $connProxy->conn;
        // TODO: chunked transfer encoding (also for outgoing data)
        // TODO: multipart parsing
        $parser = new RequestHeaderParser();
        $parser->on('headers', function (Request $request, $bodyBuffer) use ($conn, $parser, $connProxy) {
            // attach remote ip to the request as metadata
            $request->remoteAddress = $conn->getRemoteAddress();

            $conn->removeListener('data', array($parser, 'feed'));
            $conn->on('end', function () use ($request) {
                $request->emit('end');
            });
            $conn->on('data', function ($data) use ($request) {
                $request->emit('data', array($data));
            });
            $request->on('pause', function () use ($conn) {
                $conn->emit('pause');
            });
            $request->on('resume', function () use ($conn) {
                $conn->emit('resume');
            });

            $this->handleRequest($connProxy, $request, $bodyBuffer);
        });

        $conn->on('data', array($parser, 'feed'));
    }

    public function handleRequest(ConnProxy $connProxy, Request $request, $bodyBuffer)
    {
        $conn = $connProxy->conn;
        $response = new Response($conn);

        //Keepalive max requests
        $connProxy->count += 1;
        if ($connProxy->count >= self::KEEPALIVE_MAX_REQUEST) {
            $response->closeConnection = true;
        }

        $headers = $request->getHeaders();
        if (empty($headers['Connection']) || $headers['Connection'] == 'close') {
            $response->closeConnection = true;
        }

        $response->on('close', array($request, 'close'));

        //Handle keepalive
        $response->on('end', function() use ($conn, $response, $connProxy) {
            if (! $response->closeConnection) {
                $this->handleConnection($connProxy);
            }
        });

        if (!$this->listeners('request')) {
            $response->end();
            return;
        }

        $this->emit('request', array($request, $response));
        $request->emit('data', array($bodyBuffer));
    }
}
