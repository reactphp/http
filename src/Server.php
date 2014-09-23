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

        $this->io->on('connection', function ($conn) {
            // TODO: chunked transfer encoding (also for outgoing data)
            // TODO: multipart parsing

            $parser = new RequestHeaderParser();
            $parser->on('headers', function (Request $request, $bodyBuffer) use ($conn, $parser) {
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

                $this->handleRequest($conn, $parser, $request, $bodyBuffer);
            });

            $conn->on('data', array($parser, 'feed'));
            $conn->on('end', function () use ($parser) {
                $parser->removeAllListeners();
            });
        });
    }

    public function handleRequest(ConnectionInterface $conn, RequestHeaderParser $parser, Request $request, $bodyBuffer)
    {
        $response = new Response($conn);
        $response->on('close', array($request, 'close'));

        if (!$this->listeners('request')) {
            $response->end();

            return;
        }

        $response->on('end', function ($keepAlive) use ($conn, $parser) {
            $conn->removeAllListeners(); // stop data being sent to this Request instance
            if ($keepAlive) {
                $conn->on('data', array($parser, 'feed')); // resume sending data to RequestHeaderParser
                $conn->on('end', function () use ($parser) {
                    $parser->removeAllListeners();
                });
            }
            else {
                $parser->removeAllListeners();
            }
        });

        $this->emit('request', array($request, $response));
        $request->emit('data', array($bodyBuffer));
    }
}
