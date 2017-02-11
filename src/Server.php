<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Socket\ServerInterface as SocketServerInterface;
use React\Socket\ConnectionInterface;

/** @event request */
class Server extends EventEmitter implements ServerInterface
{
    private $io;

    public function __construct(SocketServerInterface $io)
    {
        $this->io = $io;
        $that = $this;

        $this->io->on('connection', function (ConnectionInterface $conn) use ($that) {
            // TODO: http 1.1 keep-alive
            // TODO: chunked transfer encoding (also for outgoing data)
            // TODO: multipart parsing

            $parser = new RequestHeaderParser();
            $parser->on('headers', function (Request $request, $bodyBuffer) use ($conn, $parser, $that) {
                // attach remote ip to the request as metadata
                $request->remoteAddress = $conn->getRemoteAddress();

                // forward pause/resume calls to underlying connection
                $request->on('pause', array($conn, 'pause'));
                $request->on('resume', array($conn, 'resume'));

                $that->handleRequest($conn, $request, $bodyBuffer);

                $conn->removeListener('data', array($parser, 'feed'));
                $conn->on('end', function () use ($request) {
                    $request->emit('end');
                });
                $conn->on('data', function ($data) use ($request) {
                    $request->emit('data', array($data));
                });
            });

            $listener = array($parser, 'feed');
            $conn->on('data', $listener);
            $parser->on('error', function() use ($conn, $listener, $that) {
                // TODO: return 400 response
                $conn->removeListener('data', $listener);
                $that->emit('error', func_get_args());
            });
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

        if ($bodyBuffer !== '') {
            $request->emit('data', array($bodyBuffer));
        }
    }
}
