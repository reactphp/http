<?php

use React\Http\Browser;
use Psr\Http\Message\ResponseInterface;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$client->get('http://google.com/')->then(function (ResponseInterface $response) {
    var_dump($response->getHeaders(), (string)$response->getBody());
});

$loop->run();
