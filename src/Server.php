<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Socket\ServerInterface as SocketServerInterface;
use React\Socket\ConnectionInterface;

/** @event request */
class Server extends EventEmitter implements ServerInterface
{
    private $io;

    private $maxHeaderSize;

    public function __construct(SocketServerInterface $io, $maxHeaderSize = 4096 /* 4 KiB */)
    {
        $this->io = $io;
        $this->maxHeaderSize = $maxHeaderSize;

        $this->io->on('connection', function (ConnectionInterface $conn) {
            // TODO: http 1.1 keep-alive
            // TODO: chunked transfer encoding (also for outgoing data)
            // TODO: multipart parsing

            $parser = new RequestHeaderParser($this->maxHeaderSize);
            $parser->on('headers', function (Request $request, $bodyBuffer) use ($conn, $parser) {
                // attach remote ip to the request as metadata
                $request->remoteAddress = $conn->getRemoteAddress();

                $this->handleRequest($conn, $request, $bodyBuffer);

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
            });
            $parser->on('error', function (\Exception $e) {
                $this->emit('error', [$e]);
            });

            $conn->on('data', array($parser, 'feed'));
        });
    }

    public function handleRequest(ConnectionInterface $conn, Request $request, $bodyBuffer)
    {
        $response = new Response($conn);
        $response->on('close', array($request, 'close'));

        if (!$this->listeners('request')) {
            $response->end();

            return;
        }

        $this->emit('request', array($request, $response));
        $request->emit('data', array($bodyBuffer));
    }
}
