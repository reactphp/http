<?php

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Stream\ReadableResourceStream;

require __DIR__ . '/../vendor/autoload.php';

if (DIRECTORY_SEPARATOR === '\\') {
    fwrite(STDERR, 'Non-blocking console I/O not supported on Windows' . PHP_EOL);
    exit(1);
}

$client = new Browser();

$in = new ReadableResourceStream(STDIN);

$url = isset($argv[1]) ? $argv[1] : 'https://httpbingo.org/post';
echo 'Sending STDIN as POST to ' . $url . '…' . PHP_EOL;

$client->post($url, array('Content-Type' => 'text/plain'), $in)->then(function (ResponseInterface $response) {
    echo (string) $response->getBody();
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
