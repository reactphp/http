<?php

// not already running an HTTP CONNECT proxy server?
// Try LeProxy.org or this:
//
// $ php examples/72-server-http-connect-proxy.php 127.0.0.1:8080
// $ http_proxy=127.0.0.1:8080 php examples/11-client-http-connect-proxy.php

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Socket\Connector;

require __DIR__ . '/../vendor/autoload.php';

// create a new HTTP CONNECT proxy client which connects to an HTTP CONNECT proxy server listening on 127.0.0.1:8080
$proxy = new Clue\React\HttpProxy\ProxyConnector(getenv('http_proxy') ?: '127.0.0.1:8080');

// create a Browser object that uses the HTTP CONNECT proxy client for connections
$connector = new Connector(array(
    'tcp' => $proxy,
    'dns' => false
));

$browser = new Browser($connector);

// demo fetching HTTP headers (or bail out otherwise)
$browser->get('https://www.google.com/')->then(function (ResponseInterface $response) {
    echo (string) $response->getBody();
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
