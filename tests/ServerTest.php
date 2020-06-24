<?php

namespace React\Tests\Http;

use React\EventLoop\Factory;
use React\Http\Server;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Deferred;
use Clue\React\Block;
use React\Promise;
use React\Http\Middleware\StreamingRequestMiddleware;
use React\Stream\ReadableStreamInterface;

final class ServerTest extends TestCase
{
    private $connection;
    private $socket;

    /**
     * @before
     */
    public function setUpConnectionMockAndSocket()
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

    public function testInvalidCallbackFunctionLeadsToException()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Server(Factory::create(), 'invalid');
    }

    public function testSimpleRequestCallsRequestHandlerOnce()
    {
        $called = null;
        $server = new Server(Factory::create(), function (ServerRequestInterface $request) use (&$called) {
            ++$called;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));
        $this->connection->emit('data', array("GET / HTTP/1.0\r\n\r\n"));

        $this->assertSame(1, $called);
    }

    /**
     * @requires PHP 5.4
     */
    public function testSimpleRequestCallsArrayRequestHandlerOnce()
    {
        $this->called = null;
        $server = new Server(Factory::create(), array($this, 'helperCallableOnce'));

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));
        $this->connection->emit('data', array("GET / HTTP/1.0\r\n\r\n"));

        $this->assertSame(1, $this->called);
    }

    public function helperCallableOnce()
    {
        ++$this->called;
    }

    public function testSimpleRequestWithMiddlewareArrayProcessesMiddlewareStack()
    {
        $called = null;
        $server = new Server(
            Factory::create(),
            function (ServerRequestInterface $request, $next) use (&$called) {
                $called = 'before';
                $ret = $next($request->withHeader('Demo', 'ok'));
                $called .= 'after';

                return $ret;
            },
            function (ServerRequestInterface $request) use (&$called) {
                $called .= $request->getHeaderLine('Demo');
            }
        );

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));
        $this->connection->emit('data', array("GET / HTTP/1.0\r\n\r\n"));

        $this->assertSame('beforeokafter', $called);
    }

    public function testPostFileUpload()
    {
        $loop = Factory::create();
        $deferred = new Deferred();
        $server = new Server($loop, function (ServerRequestInterface $request) use ($deferred) {
            $deferred->resolve($request);
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $connection = $this->connection;
        $data = $this->createPostFileUploadRequest();
        $loop->addPeriodicTimer(0.01, function ($timer) use ($loop, &$data, $connection) {
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

    public function testServerReceivesBufferedRequestByDefault()
    {
        $streaming = null;
        $server = new Server(Factory::create(), function (ServerRequestInterface $request) use (&$streaming) {
            $streaming = $request->getBody() instanceof ReadableStreamInterface;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));
        $this->connection->emit('data', array("GET / HTTP/1.0\r\n\r\n"));

        $this->assertEquals(false, $streaming);
    }

    public function testServerWithStreamingRequestMiddlewareReceivesStreamingRequest()
    {
        $streaming = null;
        $server = new Server(
            Factory::create(),
            new StreamingRequestMiddleware(),
            function (ServerRequestInterface $request) use (&$streaming) {
                $streaming = $request->getBody() instanceof ReadableStreamInterface;
            }
        );

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));
        $this->connection->emit('data', array("GET / HTTP/1.0\r\n\r\n"));

        $this->assertEquals(true, $streaming);
    }

    public function testForwardErrors()
    {
        $exception = new \Exception();
        $capturedException = null;
        $server = new Server(Factory::create(), function () use ($exception) {
            return Promise\reject($exception);
        });
        $server->on('error', function ($error) use (&$capturedException) {
            $capturedException = $error;
        });

        $server->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $data = $this->createPostFileUploadRequest();
        $this->connection->emit('data', array(implode('', $data)));

        $this->assertInstanceOf('RuntimeException', $capturedException);
        $this->assertInstanceOf('Exception', $capturedException->getPrevious());
        $this->assertSame($exception, $capturedException->getPrevious());
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

    public function provideIniSettingsForConcurrency()
    {
        return array(
            'default settings' => array(
                '128M',
                '8M',
                4
            ),
            'unlimited memory_limit limited to maximum concurrency' => array(
                '-1',
                '8M',
                100
            ),
            'unlimited post_max_size' => array(
                '128M',
                '0',
                1
            ),
            'small post_max_size limited to maximum concurrency' => array(
                '128M',
                '1k',
                100
            )
        );
    }

    /**
     * @param string $memory_limit
     * @param string $post_max_size
     * @param int    $expectedConcurrency
     * @dataProvider provideIniSettingsForConcurrency
     */
    public function testServerConcurrency($memory_limit, $post_max_size, $expectedConcurrency)
    {
        $server = new Server(Factory::create(), function () { });

        $ref = new \ReflectionMethod($server, 'getConcurrentRequestsLimit');
        $ref->setAccessible(true);

        $value = $ref->invoke($server, $memory_limit, $post_max_size);

        $this->assertEquals($expectedConcurrency, $value);
    }
}
