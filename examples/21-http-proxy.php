<?php

use Psr\Http\Message\RequestInterface;
use React\EventLoop\Factory;
use React\Http\Response;
use React\Http\Server;
use RingCentral\Psr7;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

// Note how this example uses the `Server` instead of `StreamingServer`.
// This means that this proxy buffers the whole request before "processing" it.
// As such, this is store-and-forward proxy. This could also use the advanced
// `StreamingServer` to forward the incoming request as it comes in.
$server = new Server(function (RequestInterface $request) {
    if (strpos($request->getRequestTarget(), '://') === false) {
        return new Response(
            400,
            array(
                'Content-Type' => 'text/plain'
            ),
            'This is a plain HTTP proxy'
        );
    }

    // prepare outgoing client request by updating request-target and Host header
    $host = (string)$request->getUri()->withScheme('')->withPath('')->withQuery('');
    $target = (string)$request->getUri()->withScheme('')->withHost('')->withPort(null);
    if ($target === '') {
        $target = $request->getMethod() === 'OPTIONS' ? '*' : '/';
    }
    $outgoing = $request->withRequestTarget($target)->withHeader('Host', $host);

    // pseudo code only: simply dump the outgoing request as a string
    // left up as an exercise: use an HTTP client to send the outgoing request
    // and forward the incoming response to the original client request
    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        Psr7\str($outgoing)
    );
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
