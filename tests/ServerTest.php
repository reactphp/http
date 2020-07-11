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
use React\Http\Io\IniUtil;

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
                '64K', // 8M capped at maximum
                1024
            ),
            'unlimited memory_limit has no concurrency limit' => array(
                '-1',
                '8M',
                null
            ),
            'small post_max_size results in high concurrency' => array(
                '128M',
                '1k',
                65536
            )
        );
    }

    /**
     * @param string $memory_limit
     * @param string $post_max_size
     * @param ?int   $expectedConcurrency
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

    public function testServerGetPostMaxSizeReturnsSizeFromGivenIniSetting()
    {
        $server = new Server(Factory::create(), function () { });

        $ref = new \ReflectionMethod($server, 'getMaxRequestSize');
        $ref->setAccessible(true);

        $value = $ref->invoke($server, '1k');

        $this->assertEquals(1024, $value);
    }

    public function testServerGetPostMaxSizeReturnsSizeCappedFromGivenIniSetting()
    {
        $server = new Server(Factory::create(), function () { });

        $ref = new \ReflectionMethod($server, 'getMaxRequestSize');
        $ref->setAccessible(true);

        $value = $ref->invoke($server, '1M');

        $this->assertEquals(64 * 1024, $value);
    }

    public function testServerGetPostMaxSizeFromIniIsCapped()
    {
        if (IniUtil::iniSizeToBytes(ini_get('post_max_size')) < 64 * 1024) {
            $this->markTestSkipped();
        }

        $server = new Server(Factory::create(), function () { });

        $ref = new \ReflectionMethod($server, 'getMaxRequestSize');
        $ref->setAccessible(true);

        $value = $ref->invoke($server);

        $this->assertEquals(64 * 1024, $value);
    }

    public function testConstructServerWithUnlimitedMemoryLimitDoesNotLimitConcurrency()
    {
        $old = ini_get('memory_limit');
        ini_set('memory_limit', '-1');

        $server = new Server(Factory::create(), function () { });

        ini_set('memory_limit', $old);

        $ref = new \ReflectionProperty($server, 'streamingServer');
        $ref->setAccessible(true);

        $streamingServer = $ref->getValue($server);

        $ref = new \ReflectionProperty($streamingServer, 'callback');
        $ref->setAccessible(true);

        $middlewareRunner = $ref->getValue($streamingServer);

        $ref = new \ReflectionProperty($middlewareRunner, 'middleware');
        $ref->setAccessible(true);

        $middleware = $ref->getValue($middlewareRunner);

        $this->assertTrue(is_array($middleware));
        $this->assertInstanceOf('React\Http\Middleware\RequestBodyBufferMiddleware', $middleware[0]);
    }

    public function testConstructServerWithMemoryLimitDoesLimitConcurrency()
    {
        $old = ini_get('memory_limit');
        ini_set('memory_limit', '100M');

        $server = new Server(Factory::create(), function () { });

        ini_set('memory_limit', $old);

        $ref = new \ReflectionProperty($server, 'streamingServer');
        $ref->setAccessible(true);

        $streamingServer = $ref->getValue($server);

        $ref = new \ReflectionProperty($streamingServer, 'callback');
        $ref->setAccessible(true);

        $middlewareRunner = $ref->getValue($streamingServer);

        $ref = new \ReflectionProperty($middlewareRunner, 'middleware');
        $ref->setAccessible(true);

        $middleware = $ref->getValue($middlewareRunner);

        $this->assertTrue(is_array($middleware));
        $this->assertInstanceOf('React\Http\Middleware\LimitConcurrentRequestsMiddleware', $middleware[0]);
    }
}
