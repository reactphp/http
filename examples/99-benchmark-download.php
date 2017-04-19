<?php

// $ php examples/99-benchmark-download.php 8080
// $ curl http://localhost:8080/10g.bin > /dev/null
// $ wget http://localhost:8080/10g.bin -O /dev/null
// $ ab -n10 -c10 http://localhost:8080/1g.bin
// $ docker run -it --rm --net=host jordi/ab ab -n10 -c10 http://localhost:8080/1g.bin

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Http\Response;
use Psr\Http\Message\ServerRequestInterface;
use React\Stream\ReadableStream;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);

/** A readable stream that can emit a lot of data */
class ChunkRepeater extends ReadableStream
{
    private $chunk;
    private $count;
    private $position = 0;
    private $paused = true;

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
        if (!$this->paused) {
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

    public function getSize()
    {
        return strlen($this->chunk) * $this->count;
    }
}

$server = new \React\Http\Server($socket, function (ServerRequestInterface $request) use ($loop) {
    switch ($request->getUri()->getPath()) {
        case '/':
            return new Response(
                200,
                array('Content-Type' => 'text/html'),
                '<html><a href="1g.bin">1g.bin</a><br/><a href="10g.bin">10g.bin</a></html>'
            );
        case '/1g.bin':
            $stream = new ChunkRepeater(str_repeat('.', 1000000), 1000);
            break;
        case '/10g.bin':
            $stream = new ChunkRepeater(str_repeat('.', 1000000), 10000);
            break;
        default:
            return new Response(404);
    }

    $loop->addTimer(0, array($stream, 'resume'));

    return new Response(
        200,
        array('Content-Type' => 'application/octet-data', 'Content-Length' => $stream->getSize()),
        $stream
    );
});

echo 'Listening on http://' . $socket->getAddress() . PHP_EOL;

$loop->run();
