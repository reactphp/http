<?php

use React\Http\Browser;
use Psr\Http\Message\ResponseInterface;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$client->head('http://www.github.com/clue/http-react')->then(function (ResponseInterface $response) {
    var_dump($response->getHeaders(), (string)$response->getBody());
});

$client->get('http://google.com/')->then(function (ResponseInterface $response) {
    var_dump($response->getHeaders(), (string)$response->getBody());
});

$client->get('http://www.lueck.tv/psocksd')->then(function (ResponseInterface $response) {
    var_dump($response->getHeaders(), (string)$response->getBody());
});

$loop->run();
