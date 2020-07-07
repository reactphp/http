<?php

// a) simple 1 MB upload benchmark against public HTTP endpoint
// $ php examples/92-client-benchmark-upload.php http://httpbin.org/post 1
//
// b) local 10 GB upload benchmark against localhost address to avoid network overhead
//
// b1) first run example HTTP server:
// $ php examples/63-server-streaming-request.php 8080
//
// b2) run HTTP client sending a 10 GB upload
// $ php examples/92-client-benchmark-upload.php http://localhost:8080/ 10000

use React\Http\Browser;
use Evenement\EventEmitter;
use Psr\Http\Message\ResponseInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

require __DIR__ . '/../vendor/autoload.php';

if (extension_loaded('xdebug')) {
    echo 'NOTICE: The "xdebug" extension is loaded, this has a major impact on performance.' . PHP_EOL;
}

/** A readable stream that can emit a lot of data */
class ChunkRepeater extends EventEmitter implements ReadableStreamInterface
{
    private $chunk;
    private $count;
    private $position = 0;
    private $paused = true;
    private $closed = false;

    public function __construct($chunk, $count)
    {
        $this->chunk = $chunk;
        $this->count = $count;
    }

    public function pause()
    {
        $this->paused = true;
    }

    public function resume()
    {
        if (!$this->paused || $this->closed) {
            return;
        }

        // keep emitting until stream is paused
        $this->paused = false;
        while ($this->position < $this->count && !$this->paused) {
            ++$this->position;
            $this->emit('data', array($this->chunk));
        }

        // end once the last chunk has been written
        if ($this->position >= $this->count) {
            $this->emit('end');
            $this->close();
        }
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        return Util::pipe($this, $dest, $options);
    }

    public function isReadable()
    {
        return !$this->closed;
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->count = 0;
        $this->paused = true;
        $this->emit('close');
    }

    public function getPosition()
    {
        return $this->position * strlen($this->chunk);
    }
}

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$url = isset($argv[1]) ? $argv[1] : 'http://httpbin.org/post';
$n = isset($argv[2]) ? $argv[2] : 10;
$source = new ChunkRepeater(str_repeat('x', 1000000), $n);
$loop->futureTick(function () use ($source) {
    $source->resume();
});

echo 'POSTing ' . $n . ' MB to ' . $url . PHP_EOL;

$start = microtime(true);
$report = $loop->addPeriodicTimer(0.05, function () use ($source, $start) {
    printf("\r%d bytes in %0.3fs...", $source->getPosition(), microtime(true) - $start);
});

$client->post($url, array('Content-Length' => $n * 1000000), $source)->then(function (ResponseInterface $response) use ($source, $report, $loop, $start) {
    $now = microtime(true);
    $loop->cancelTimer($report);

    printf("\r%d bytes in %0.3fs => %.1f MB/s\n", $source->getPosition(), $now - $start, $source->getPosition() / ($now - $start) / 1000000);

    echo rtrim(preg_replace('/x{5,}/','x…', (string) $response->getBody()), PHP_EOL) . PHP_EOL;
}, function ($e) use ($loop, $report) {
    $loop->cancelTimer($report);
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$loop->run();
