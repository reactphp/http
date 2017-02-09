<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Socket\ServerInterface as SocketServerInterface;
use React\Socket\ConnectionInterface;

/** @event request */
class Server extends EventEmitter implements ServerInterface
{
    private $io;
    private $params = [];

    public function __construct(SocketServerInterface $io, $params=[])
    {
        $this->io = $io;
        $this->params = new ParamBag(null, false, $params);

        $this->io->on('connection', function (ConnectionInterface $conn) {
            // TODO: http 1.1 keep-alive
            // TODO: chunked transfer encoding (also for outgoing data)
            // TODO: multipart parsing

            $parser = new RequestHeaderParser();
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

            $listener = [$parser, 'feed'];
            $conn->on('data', $listener);
            $parser->on('error', function() use ($conn, $listener) {
                // TODO: return 400 response
                $conn->removeListener('data', $listener);
                $this->emit('error', func_get_args());
            });
        });
    }

    public function handleRequest(ConnectionInterface $conn, Request $request, $bodyBuffer)
    {
        $response = new Response($conn, $this->params);
        $response->on('close', array($request, 'close'));

        if (!$this->listeners('request')) {
            $response->end();

            return;
        }

        $this->emit('request', array($request, $response));
        $request->emit('data', array($bodyBuffer));
    }
}
