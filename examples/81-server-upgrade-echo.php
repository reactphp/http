<?php

/*
Here's the gist to get you started:

$ telnet localhost 1080
> GET / HTTP/1.1
> Upgrade: echo
>
< HTTP/1.1 101 Switching Protocols
< Upgrade: echo
< Connection: upgrade
<
> hello
< hello
> world
< world
*/

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Message\Response;
use React\Http\Server;
use React\Stream\ThroughStream;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

// Note how this example uses the `Server` without the `StreamingRequestMiddleware`.
// The initial incoming request does not contain a body and we upgrade to a
// stream object below.
$server = new Server($loop, function (ServerRequestInterface $request) use ($loop) {
    if ($request->getHeaderLine('Upgrade') !== 'echo' || $request->getProtocolVersion() === '1.0') {
        return new Response(
            StatusCodeInterface::STATUS_UPGRADE_REQUIRED,
            array(
                'Upgrade' => 'echo'
            ),
            '"Upgrade: echo" required'
        );
    }

    // simply return a duplex ThroughStream here
    // it will simply emit any data that is sent to it
    // this means that any Upgraded data will simply be sent back to the client
    $stream = new ThroughStream();

    $loop->addTimer(0, function () use ($stream) {
        $stream->write("Hello! Anything you send will be piped back." . PHP_EOL);
    });

    return new Response(
        StatusCodeInterface::STATUS_SWITCHING_PROTOCOLS,
        array(
            'Upgrade' => 'echo'
        ),
        $stream
    );
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
