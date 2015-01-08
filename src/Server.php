<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Socket\ServerInterface as SocketServerInterface;
use React\Socket\ConnectionInterface;

/** @event request */
class Server extends EventEmitter implements ServerInterface
{
    private $io;
    private $loop;

    public function __construct($host = '0.0.0.0', $port = '8080', $loop = null)
    {
        if ($loop === null)
            $loop = \React\EventLoop\Factory::create();

        $this->loop = $loop;

        $this->io = new \React\Socket\Server($loop);
        $this->io->listen($port, $host);

        $this->io->on('connection', array($this, 'handleConnection'));
    }

    public function handleConnection(ConnectionInterface $conn)
    {
        // TODO: http 1.1 keep-alive
        // TODO: chunked transfer encoding (also for outgoing data)

        $parser = new RequestParser();
        $parser->on('request', function (Request $request) use ($conn, $parser) {
            // attach remote ip to the request as metadata
            $request->remoteAddress = $conn->getRemoteAddress();

            $this->handleRequest($conn, $request);

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

        $parser->on('trigger', function($closure) {
            $this->loop->nextTick($closure);
        });

        $conn->on('data', array($parser, 'feed'));
    }

    public function handleRequest(ConnectionInterface $conn, Request $request)
    {
        $response = new Response($conn);
        $response->on('close', array($request, 'close'));

        if (!$this->listeners('request')) {
            $response->end();

            return;
        }
        $this->emit('request', array($request, $response));
    }

    public function run()
    {
        $this->loop->run();
    }

    public function stop()
    {
        $this->io->shutdown();
        $this->loop->stop();
    }
}
