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

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\Message\Response;
use React\Stream\ThroughStream;

require __DIR__ . '/../vendor/autoload.php';

// Note how this example uses the `HttpServer` without the `StreamingRequestMiddleware`.
// The initial incoming request does not contain a body and we upgrade to a
// stream object below.
$http = new React\Http\HttpServer(function (ServerRequestInterface $request) {
    if ($request->getHeaderLine('Upgrade') !== 'echo' || $request->getProtocolVersion() === '1.0') {
        return new Response(
            426,
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

    Loop::addTimer(0, function () use ($stream) {
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

$socket = new React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$http->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
