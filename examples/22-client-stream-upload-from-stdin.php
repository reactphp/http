<?php

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Stream\ReadableResourceStream;
use RingCentral\Psr7;

require __DIR__ . '/../vendor/autoload.php';

if (DIRECTORY_SEPARATOR === '\\') {
    fwrite(STDERR, 'Non-blocking console I/O not supported on Windows' . PHP_EOL);
    exit(1);
}

$client = new Browser();

$in = new ReadableResourceStream(STDIN);

$url = isset($argv[1]) ? $argv[1] : 'https://httpbin.org/post';
echo 'Sending STDIN as POST to ' . $url . '…' . PHP_EOL;

$client->post($url, array(), $in)->then(function (ResponseInterface $response) {
    echo 'Received' . PHP_EOL . Psr7\str($response);
}, 'printf');
