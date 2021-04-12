<?php

// $ php examples/71-server-http-proxy.php 8080
// $ curl -v --proxy http://localhost:8080 http://reactphp.org/

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\RequestInterface;
use React\Http\Message\Response;
use RingCentral\Psr7;

require __DIR__ . '/../vendor/autoload.php';

// Note how this example uses the `HttpServer` without the `StreamingRequestMiddleware`.
// This means that this proxy buffers the whole request before "processing" it.
// As such, this is store-and-forward proxy. This could also use the advanced
// `StreamingRequestMiddleware` to forward the incoming request as it comes in.
$http = new React\Http\HttpServer(function (RequestInterface $request) {
    if (strpos($request->getRequestTarget(), '://') === false) {
        return new Response(
            StatusCodeInterface::STATUS_BAD_REQUEST,
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
        StatusCodeInterface::STATUS_OK,
        array(
            'Content-Type' => 'text/plain'
        ),
        Psr7\str($outgoing)
    );
});

$socket = new React\Socket\SocketServer(isset($argv[1]) ? $argv[1] : '0.0.0.0:0');
$http->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
