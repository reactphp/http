<?php

// a) simple download benchmark against public HTTP endpoint:
// $ php examples/91-client-benchmark-download.php http://httpbingo.org/get

// b) local 10 GB download benchmark against localhost address to avoid network overhead
//
// b1) first run example HTTP server:
// $ php examples/99-server-benchmark-download.php 8080
//
// b2) run HTTP client receiving a 10 GB download:
// $ php examples/91-client-benchmark-download.php http://localhost:8080/10g.bin

use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\Http\Browser;
use React\Stream\ReadableStreamInterface;

$url = isset($argv[1]) ? $argv[1] : 'http://google.com/';

require __DIR__ . '/../vendor/autoload.php';

if (extension_loaded('xdebug')) {
    echo 'NOTICE: The "xdebug" extension is loaded, this has a major impact on performance.' . PHP_EOL;
}

$client = new Browser();

echo 'Requesting ' . $url . '…' . PHP_EOL;

$client->requestStreaming('GET', $url)->then(function (ResponseInterface $response) {
    echo 'Received ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase() . PHP_EOL;

    $stream = $response->getBody();
    assert($stream instanceof ReadableStreamInterface);

    // count number of bytes received
    $bytes = 0;
    $stream->on('data', function ($chunk) use (&$bytes) {
        $bytes += strlen($chunk);
    });

    // report progress every 0.1s
    $timer = Loop::addPeriodicTimer(0.1, function () use (&$bytes) {
        echo "\rDownloaded " . $bytes . " bytes…";
    });

    // report results once the stream closes
    $time = microtime(true);
    $stream->on('close', function() use (&$bytes, $timer, $time) {
        Loop::cancelTimer($timer);

        $time = microtime(true) - $time;

        echo "\r" . 'Downloaded ' . $bytes . ' bytes in ' . round($time, 3) . 's => ' . round($bytes / $time / 1000000, 1) . ' MB/s' . PHP_EOL;
    });
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
