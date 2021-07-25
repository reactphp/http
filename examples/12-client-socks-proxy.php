<?php

// not already running a SOCKS proxy server?
// Try LeProxy.org or this:
//
// $ ssh -D 1080 alice@example.com
// $ socks_proxy=127.0.0.1:1080 php examples/12-client-socks-proxy.php

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Socket\Connector;

require __DIR__ . '/../vendor/autoload.php';

// create a new SOCKS proxy client which connects to a SOCKS proxy server listening on 127.0.0.1:1080
$proxy = new Clue\React\Socks\Client(getenv('socks_proxy') ?: '127.0.0.1:1080');

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
