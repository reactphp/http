<?php

use React\Http\Browser;
use Psr\Http\Message\ResponseInterface;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$data = array(
    'name' => array(
        'first' => 'Alice',
        'name' => 'Smith'
    ),
    'email' => 'alice@example.com'
);

$client->post(
    'https://httpbin.org/post',
    array(
        'Content-Type' => 'application/json'
    ),
    json_encode($data)
)->then(function (ResponseInterface $response) {
    echo (string)$response->getBody();
}, 'printf');

$loop->run();
