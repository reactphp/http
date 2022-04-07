<?php

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Socket\FixedUriConnector;
use React\Socket\UnixConnector;
use RingCentral\Psr7;

require __DIR__ . '/../vendor/autoload.php';

// create a Browser object that uses the a Unix Domain Sockets (UDS) path for all requests
$connector = new FixedUriConnector(
    'unix:///var/run/docker.sock',
    new UnixConnector()
);

$browser = new Browser($connector);

// demo fetching HTTP headers (or bail out otherwise)
$browser->get('http://localhost/info')->then(function (ResponseInterface $response) {
    echo Psr7\str($response);
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
