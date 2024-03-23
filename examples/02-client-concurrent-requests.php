<?php

use React\Http\Browser;
use Psr\Http\Message\ResponseInterface;

require __DIR__ . '/../vendor/autoload.php';

$client = new Browser();

$client->head('http://www.github.com/clue/http-react')->then(function (ResponseInterface $response) {
    echo (string) $response->getBody();
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$client->get('http://google.com/')->then(function (ResponseInterface $response) {
    echo (string) $response->getBody();
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$client->get('http://www.lueck.tv/psocksd')->then(function (ResponseInterface $response) {
    echo (string) $response->getBody();
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
