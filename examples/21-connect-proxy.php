<?php

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Http\Response;
use Psr\Http\Message\RequestInterface;
use React\SocketClient\Connector;
use React\SocketClient\ConnectionInterface;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$connector = new Connector($loop);

$server = new \React\Http\Server($socket, function (RequestInterface $request) use ($connector) {
    if ($request->getMethod() !== 'CONNECT') {
        return new Response(
            405,
            array('Content-Type' => 'text/plain', 'Allow' => 'CONNECT'),
            'This is a HTTP CONNECT (secure HTTPS) proxy'
        );
    }

    // pause consuming request body
    $body = $request->getBody();
    $body->pause();

    $buffer = '';
    $body->on('data', function ($chunk) use (&$buffer) {
        $buffer .= $chunk;
    });

    // try to connect to given target host
    $promise = $connector->connect($request->getRequestTarget())->then(
        function (ConnectionInterface $remote) use ($body, &$buffer) {
            // connection established => forward data
            $body->pipe($remote);
            $body->resume();

            if ($buffer !== '') {
                $remote->write($buffer);
                $buffer = '';
            }

            return new Response(
                200,
                array(),
                $remote
            );
        },
        function ($e) {
            return new Response(
                502,
                array('Content-Type' => 'text/plain'),
                'Unable to connect: ' . $e->getMessage()
            );
        }
    );

    // cancel pending connection if request closes prematurely
    $body->on('close', function () use ($promise) {
        $promise->cancel();
    });

    return $promise;
});

//$server->on('error', 'printf');

echo 'Listening on http://' . $socket->getAddress() . PHP_EOL;

$loop->run();
