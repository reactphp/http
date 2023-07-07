<?php

// concurrently request a number of URLs.
// return immediately once the first is completed, cancel all others.

use React\Http\Browser;
use Psr\Http\Message\ResponseInterface;

require __DIR__ . '/../vendor/autoload.php';

$client = new Browser();

$promises = array(
    $client->head('http://www.github.com/clue/http-react'),
    $client->get('https://httpbingo.org/'),
    $client->get('https://google.com'),
    $client->get('http://www.lueck.tv/psocksd'),
    $client->get('http://httpbingo.org/absolute-redirect/5')
);

React\Promise\any($promises)->then(function (ResponseInterface $response) use ($promises) {
    // first response arrived => cancel all other pending requests
    foreach ($promises as $promise) {
        $promise->cancel();
    }

    var_dump($response->getHeaders());
    echo PHP_EOL . $response->getBody();
}, function ($e) {
    // Promise v1 and v2 reject with an array of Exceptions here, Promise v3 will use an Exception object instead
    if (is_array($e)) {
        $e = end($e);
    }
    assert($e instanceof Exception);

    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
