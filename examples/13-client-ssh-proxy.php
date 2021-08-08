<?php

// $ ssh_proxy=alice@example.com php examples/13-client-ssh-proxy.php

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Socket\Connector;

require __DIR__ . '/../vendor/autoload.php';

// create a new SSH proxy client which connects to a SSH server listening on alice@localhost
$proxy = new Clue\React\SshProxy\SshSocksConnector(getenv('ssh_proxy') ?: 'alice@localhost');

// create a Browser object that uses the SSH proxy client for connections
$connector = new Connector(array(
    'tcp' => $proxy,
    'dns' => false
));

$browser = new Browser($connector);

// demo fetching HTTP headers (or bail out otherwise)
$browser->get('https://www.google.com/')->then(function (ResponseInterface $response) {
    echo RingCentral\Psr7\str($response);
}, 'printf');
