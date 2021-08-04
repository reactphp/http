<?php

// not already running a SOCKS proxy server?
// Try LeProxy.org or this: `ssh -D 1080 localhost`

use Clue\React\Socks\Client as SocksClient;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Socket\Connector;

require __DIR__ . '/../vendor/autoload.php';

// create a new SOCKS proxy client which connects to a SOCKS proxy server listening on localhost:1080
$proxy = new SocksClient('127.0.0.1:1080', new Connector());

// create a Browser object that uses the SOCKS proxy client for connections
$connector = new Connector(array(
    'tcp' => $proxy,
    'dns' => false
));

$browser = new Browser($connector);

// demo fetching HTTP headers (or bail out otherwise)
$browser->get('https://www.google.com/')->then(function (ResponseInterface $response) {
    echo RingCentral\Psr7\str($response);
}, 'printf');
