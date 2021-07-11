<?php

use Clue\React\SshProxy\SshSocksConnector;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\Http\Browser;
use React\Socket\Connector;

require __DIR__ . '/../vendor/autoload.php';

// create a new SSH proxy client which connects to a SSH server listening on localhost:22
// You can pass any SSH server address as first argument, e.g. user@example.com
$proxy = new SshSocksConnector(isset($argv[1]) ? $argv[1] : 'localhost:22', Loop::get());

// create a Browser object that uses the SSH proxy client for connections
$connector = new Connector(null, array(
    'tcp' => $proxy,
    'dns' => false
));
$browser = new Browser(null, $connector);

// demo fetching HTTP headers (or bail out otherwise)
$browser->get('https://www.google.com/')->then(function (ResponseInterface $response) {
    echo RingCentral\Psr7\str($response);
}, 'printf');
