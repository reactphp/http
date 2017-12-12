<?php

/*
Here's the gist to get you started:

$ telnet localhost 1080
> GET / HTTP/1.1
> Upgrade: chat
>
< HTTP/1.1 101 Switching Protocols
< Upgrade: chat
< Connection: upgrade
<
> hello
< user123: hello
> world
< user123: world

Hint: try this with multiple connections :)
*/

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Response;
use React\Http\Server;
use React\Stream\CompositeStream;
use React\Stream\ThroughStream;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

// simply use a shared duplex ThroughStream for all clients
// it will simply emit any data that is sent to it
// this means that any Upgraded data will simply be sent back to the client
$chat = new ThroughStream();

// Note how this example uses the `Server` instead of `StreamingServer`.
// The initial incoming request does not contain a body and we upgrade to a
// stream object below.
$server = new Server(function (ServerRequestInterface $request) use ($loop, $chat) {
    if ($request->getHeaderLine('Upgrade') !== 'chat' || $request->getProtocolVersion() === '1.0') {
        return new Response(
            426,
            array(
                'Upgrade' => 'chat'
            ),
            '"Upgrade: chat" required'
        );
    }

    // user stream forwards chat data and accepts incoming data
    $out = $chat->pipe(new ThroughStream());
    $in = new ThroughStream();
    $stream = new CompositeStream(
        $out,
        $in
    );

    // assign some name for this new connection
    $username = 'user' . mt_rand();

    // send anything that is received to the whole channel
    $in->on('data', function ($data) use ($username, $chat) {
        $data = trim(preg_replace('/[^\w \.\,\-\!\?]/u', '', $data));

        $chat->write($username . ': ' . $data . PHP_EOL);
    });

    // say hello to new user
    $loop->addTimer(0, function () use ($chat, $username, $out) {
        $out->write('Welcome to this chat example, ' . $username . '!' . PHP_EOL);
        $chat->write($username . ' joined' . PHP_EOL);
    });

    // send goodbye to channel once connection closes
    $stream->on('close', function () use ($username, $chat) {
        $chat->write($username . ' left' . PHP_EOL);
    });

    return new Response(
        101,
        array(
            'Upgrade' => 'chat'
        ),
        $stream
    );
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
