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

use React\EventLoop\Factory;
use React\Http\Server;
use React\Http\Response;
use Psr\Http\Message\ServerRequestInterface;
use React\Stream\ReadableStream;
use React\Stream\ThroughStream;
use React\Stream\CompositeStream;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);

$server = new Server($socket, function (ServerRequestInterface $request) use ($loop) {
    if ($request->getHeaderLine('Upgrade') !== 'echo' || $request->getProtocolVersion() === '1.0') {
        return new Response(426, array('Upgrade' => 'echo'), '"Upgrade: echo" required');
    }

    // simply return a duplex ThroughStream here
    // it will simply emit any data that is sent to it
    // this means that any Upgraded data will simply be sent back to the client
    $stream = new ThroughStream();

    $loop->addTimer(0, function () use ($stream) {
        $stream->write("Hello! Anything you send will be piped back." . PHP_EOL);
    });

    return new Response(
        101,
        array(
            'Upgrade' => 'echo'
        ),
        $stream
    );
});

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
