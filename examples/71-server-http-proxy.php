<?php

// $ php examples/71-server-http-proxy.php 8080
// $ curl -v --proxy http://localhost:8080 http://reactphp.org/

use Psr\Http\Message\RequestInterface;
use React\EventLoop\Factory;
use React\Http\Message\ResponseFactory;
use React\Http\Server;
use RingCentral\Psr7;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

// Note how this example uses the `Server` without the `StreamingRequestMiddleware`.
// This means that this proxy buffers the whole request before "processing" it.
// As such, this is store-and-forward proxy. This could also use the advanced
// `StreamingRequestMiddleware` to forward the incoming request as it comes in.
$server = new Server($loop, function (RequestInterface $request) {
    if (strpos($request->getRequestTarget(), '://') === false) {
        return ResponseFactory::plain('This is a plain HTTP proxy')->withStatus(400);
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
    return ResponseFactory::plain(Psr7\str($outgoing));
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
