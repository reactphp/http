<?php

use React\Http\Browser;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\FixedUriConnector;
use React\Socket\UnixConnector;
use RingCentral\Psr7;

require __DIR__ . '/../vendor/autoload.php';

$loop = LoopFactory::create();

// create a Browser object that uses the a Unix Domain Sockets (UDS) path for all requests
$connector = new FixedUriConnector(
    'unix:///var/run/docker.sock',
    new UnixConnector($loop)
);

$browser = new Browser($loop, $connector);

// demo fetching HTTP headers (or bail out otherwise)
$browser->get('http://localhost/info')->then(function (ResponseInterface $response) {
    echo Psr7\str($response);
}, 'printf');

$loop->run();
