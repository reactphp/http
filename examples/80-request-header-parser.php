<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Response;
use React\Http\RequestHeaderParser;
use React\Http\RequestHeaderParserFactory;
use React\Http\Server;
use React\Socket\ConnectionInterface;

require __DIR__ . '/../vendor/autoload.php';

class CustomRequestHeaderSizeFactory extends RequestHeaderParserFactory
{

    protected $size;

    public function __construct($size = 1024)
    {
        $this->size = $size;
    }

    public function create(ConnectionInterface $conn)
    {
        $uriLocal = $this->getUriLocal($conn);
        $uriRemote = $this->getUriRemote($conn);

        return new RequestHeaderParser($uriLocal, $uriRemote, $this->size);
    }
}

$loop = Factory::create();

$server = new Server(function (ServerRequestInterface $request) {
    return new Response(200);
}, new CustomRequestHeaderSizeFactory(1024 * 16)); // 16MB

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
