<?php

namespace React\Tests\Http;

use React\EventLoop\Factory;
use React\EventLoop\Timer\TimerInterface;
use React\Http\MiddlewareRunner;
use React\Http\Server;
use React\Http\StreamingServer;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use React\Promise\Deferred;
use Clue\React\Block;
use React\Stream\ThroughStream;
use React\Promise\Promise;

final class ServerTest extends TestCase
{
    private $connection;
    private $socket;

    public function setUp()
    {
        $this->connection = $this->getMockBuilder('React\Socket\Connection')
            ->disableOriginalConstructor()
            ->setMethods(
                array(
                    'write',
                    'end',
                    'close',
                    'pause',
                    'resume',
                    'isReadable',
                    'isWritable',
                    'getRemoteAddress',
                    'getLocalAddress',
                    'pipe'
                )
            )
            ->getMock();

        $this->connection->method('isWritable')->willReturn(true);
        $this->connection->method('isReadable')->willReturn(true);

        $this->socket = new SocketServerStub();
    }

    public function testPostFileUpload()
    {
        $loop = Factory::create();
        $deferred = new Deferred();
        $server = new Server(function (ServerRequestInterface $request) use ($deferred) {
            $deferred->resolve($request);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $connection = $this->connection;
        $data = $this->createPostFileUploadRequest();
        $loop->addPeriodicTimer(0.01, function (TimerInterface $timer) use ($loop, &$data, $connection) {
            $line = array_shift($data);
            $connection->emit('data', array($line));

            if (count($data) === 0) {
                $loop->cancelTimer($timer);
            }
        });

        $parsedRequest = Block\await($deferred->promise(), $loop);
        $this->assertNotEmpty($parsedRequest->getUploadedFiles());
        $this->assertEmpty($parsedRequest->getParsedBody());

        $files = $parsedRequest->getUploadedFiles();

        $this->assertTrue(isset($files['file']));
        $this->assertCount(1, $files);

        $this->assertSame('hello.txt', $files['file']->getClientFilename());
        $this->assertSame('text/plain', $files['file']->getClientMediaType());
        $this->assertSame("hello\r\n", (string)$files['file']->getStream());
    }

    private function createPostFileUploadRequest()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data = array();
        $data[] = "POST / HTTP/1.1\r\n";
        $data[] = "Content-Type: multipart/form-data; boundary=" . $boundary . "\r\n";
        $data[] = "Content-Length: 220\r\n";
        $data[] = "\r\n";
        $data[]  = "--$boundary\r\n";
        $data[] = "Content-Disposition: form-data; name=\"file\"; filename=\"hello.txt\"\r\n";
        $data[] = "Content-type: text/plain\r\n";
        $data[] = "\r\n";
        $data[] = "hello\r\n";
        $data[] = "\r\n";
        $data[] = "--$boundary--\r\n";

        return $data;
    }
}
