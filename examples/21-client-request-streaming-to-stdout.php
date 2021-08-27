<?php

use React\Http\Browser;
use Psr\Http\Message\ResponseInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableResourceStream;
use RingCentral\Psr7;

require __DIR__ . '/../vendor/autoload.php';

if (DIRECTORY_SEPARATOR === '\\') {
    fwrite(STDERR, 'Non-blocking console I/O not supported on Windows' . PHP_EOL);
    exit(1);
}

$client = new Browser();

$out = new WritableResourceStream(STDOUT);
$info = new WritableResourceStream(STDERR);

$url = isset($argv[1]) ? $argv[1] : 'http://google.com/';
$info->write('Requesting ' . $url . '…' . PHP_EOL);

$client->requestStreaming('GET', $url)->then(function (ResponseInterface $response) use ($info, $out) {
    $info->write('Received' . PHP_EOL . Psr7\str($response));

    $body = $response->getBody();
    assert($body instanceof ReadableStreamInterface);
    $body->pipe($out);
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
