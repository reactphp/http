<?php

namespace React\Tests\Http;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Io\IniUtil;
use React\Http\Io\StreamingServer;
use React\Http\Middleware\InactiveConnectionTimeoutMiddleware;
use React\Http\Middleware\StreamingRequestMiddleware;
use React\Promise;
use React\Promise\Deferred;
use React\Stream\ReadableStreamInterface;

final class HttpServerTest extends TestCase
{
    private $connection;
    private $socket;

    /** @var ?int */
    private $called = null;

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

    public function testConstructWithoutLoopAssignsLoopAutomatically()
    {
        $http = new HttpServer(function () { });

        $ref = new \ReflectionProperty($http, 'streamingServer');
        $ref->setAccessible(true);
        $streamingServer = $ref->getValue($http);

        $ref = new \ReflectionProperty($streamingServer, 'clock');
        $ref->setAccessible(true);
        $clock = $ref->getValue($streamingServer);

        $ref = new \ReflectionProperty($streamingServer, 'parser');
        $ref->setAccessible(true);
        $parser = $ref->getValue($streamingServer);

        $ref = new \ReflectionProperty($clock, 'loop');
        $ref->setAccessible(true);
        $loop = $ref->getValue($clock);

        $this->assertInstanceOf('React\EventLoop\LoopInterface', $loop);
    }

    public function testInvalidCallbackFunctionLeadsToException()
    {
        $this->setExpectedException('InvalidArgumentException');
        new HttpServer('invalid');
    }

    public function testSimpleRequestCallsRequestHandlerOnce()
    {
        $called = null;
        $http = new HttpServer(function (ServerRequestInterface $request) use (&$called) {
            ++$called;
        });

        $http->listen($this->socket);
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
        $http = new HttpServer(array($this, 'helperCallableOnce'));

        $http->listen($this->socket);
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
        $http = new HttpServer(
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

        $http->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));
        $this->connection->emit('data', array("GET / HTTP/1.0\r\n\r\n"));

        $this->assertSame('beforeokafter', $called);
    }

    public function testPostFormData()
    {
        $deferred = new Deferred();
        $http = new HttpServer(function (ServerRequestInterface $request) use ($deferred) {
            $deferred->resolve($request);
        });

        $http->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));
        $this->connection->emit('data', array("POST / HTTP/1.0\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: 7\r\n\r\nfoo=bar"));

        $request = \React\Async\await($deferred->promise());
        assert($request instanceof ServerRequestInterface);

        $form = $request->getParsedBody();

        $this->assertTrue(isset($form['foo']));
        $this->assertEquals('bar', $form['foo']);

        $this->assertEquals(array(), $request->getUploadedFiles());

        $body = $request->getBody();

        $this->assertSame(7, $body->getSize());
        $this->assertSame(7, $body->tell());
        $this->assertSame('foo=bar', (string) $body);
    }

    public function testPostFileUpload()
    {
        $deferred = new Deferred();
        $http = new HttpServer(function (ServerRequestInterface $request) use ($deferred) {
            $deferred->resolve($request);
        });

        $http->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        $connection = $this->connection;
        $data = $this->createPostFileUploadRequest();
        Loop::addPeriodicTimer(0.01, function ($timer) use (&$data, $connection) {
            $line = array_shift($data);
            $connection->emit('data', array($line));

            if (count($data) === 0) {
                Loop::cancelTimer($timer);
            }
        });

        $request = \React\Async\await($deferred->promise());
        assert($request instanceof ServerRequestInterface);

        $this->assertEmpty($request->getParsedBody());

        $this->assertNotEmpty($request->getUploadedFiles());

        $files = $request->getUploadedFiles();

        $this->assertTrue(isset($files['file']));
        $this->assertCount(1, $files);

        $this->assertSame('hello.txt', $files['file']->getClientFilename());
        $this->assertSame('text/plain', $files['file']->getClientMediaType());
        $this->assertSame("hello\r\n", (string)$files['file']->getStream());

        $body = $request->getBody();

        $this->assertSame(220, $body->getSize());
        $this->assertSame(220, $body->tell());
    }

    public function testPostJsonWillNotBeParsedByDefault()
    {
        $deferred = new Deferred();
        $http = new HttpServer(function (ServerRequestInterface $request) use ($deferred) {
            $deferred->resolve($request);
        });

        $http->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));
        $this->connection->emit('data', array("POST / HTTP/1.0\r\nContent-Type: application/json\r\nContent-Length: 6\r\n\r\n[true]"));

        $request = \React\Async\await($deferred->promise());
        assert($request instanceof ServerRequestInterface);

        $this->assertNull($request->getParsedBody());

        $this->assertSame(array(), $request->getUploadedFiles());

        $body = $request->getBody();

        $this->assertSame(6, $body->getSize());
        $this->assertSame(0, $body->tell());
        $this->assertSame('[true]', (string) $body);
    }

    public function testServerReceivesBufferedRequestByDefault()
    {
        $streaming = null;
        $http = new HttpServer(function (ServerRequestInterface $request) use (&$streaming) {
            $streaming = $request->getBody() instanceof ReadableStreamInterface;
        });

        $http->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));
        $this->connection->emit('data', array("GET / HTTP/1.0\r\n\r\n"));

        $this->assertEquals(false, $streaming);
    }

    public function testServerWithStreamingRequestMiddlewareReceivesStreamingRequest()
    {
        $streaming = null;
        $http = new HttpServer(
            new StreamingRequestMiddleware(),
            function (ServerRequestInterface $request) use (&$streaming) {
                $streaming = $request->getBody() instanceof ReadableStreamInterface;
            }
        );

        $http->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));
        $this->connection->emit('data', array("GET / HTTP/1.0\r\n\r\n"));

        $this->assertEquals(true, $streaming);
    }

    public function testIdleConnectionWillBeClosedAfterConfiguredTimeout()
    {
        $this->connection->expects($this->once())->method('close');

        $http = new HttpServer(Loop::get(), new InactiveConnectionTimeoutMiddleware(0.1), $this->expectCallableNever());

        $http->listen($this->socket);
        $this->socket->emit('connection', array($this->connection));

        Loop::run();
    }

    public function testForwardErrors()
    {
        $exception = new \Exception();
        $capturedException = null;
        $http = new HttpServer(function () use ($exception) {
            return Promise\reject($exception);
        });
        $http->on('error', function ($error) use (&$capturedException) {
            $capturedException = $error;
        });

        $http->listen($this->socket);
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
        $data[] = "Host: localhost\r\n";
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
        $http = new HttpServer(function () { });

        $ref = new \ReflectionMethod($http, 'getConcurrentRequestsLimit');
        $ref->setAccessible(true);

        $value = $ref->invoke($http, $memory_limit, $post_max_size);

        $this->assertEquals($expectedConcurrency, $value);
    }

    public function testServerGetPostMaxSizeReturnsSizeFromGivenIniSetting()
    {
        $http = new HttpServer(function () { });

        $ref = new \ReflectionMethod($http, 'getMaxRequestSize');
        $ref->setAccessible(true);

        $value = $ref->invoke($http, '1k');

        $this->assertEquals(1024, $value);
    }

    public function testServerGetPostMaxSizeReturnsSizeCappedFromGivenIniSetting()
    {
        $http = new HttpServer(function () { });

        $ref = new \ReflectionMethod($http, 'getMaxRequestSize');
        $ref->setAccessible(true);

        $value = $ref->invoke($http, '1M');

        $this->assertEquals(64 * 1024, $value);
    }

    public function testServerGetPostMaxSizeFromIniIsCapped()
    {
        if (IniUtil::iniSizeToBytes(ini_get('post_max_size')) < 64 * 1024) {
            $this->markTestSkipped();
        }

        $http = new HttpServer(function () { });

        $ref = new \ReflectionMethod($http, 'getMaxRequestSize');
        $ref->setAccessible(true);

        $value = $ref->invoke($http);

        $this->assertEquals(64 * 1024, $value);
    }

    public function testConstructServerWithUnlimitedMemoryLimitDoesNotLimitConcurrency()
    {
        $old = ini_get('memory_limit');
        ini_set('memory_limit', '-1');

        $http = new HttpServer(function () { });

        ini_set('memory_limit', $old);

        $ref = new \ReflectionProperty($http, 'streamingServer');
        $ref->setAccessible(true);

        $streamingServer = $ref->getValue($http);

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
        if (@ini_set('memory_limit', '128M') === false) {
            $this->markTestSkipped('Unable to change memory limit');
        }

        $http = new HttpServer(function () { });

        ini_set('memory_limit', $old);

        $ref = new \ReflectionProperty($http, 'streamingServer');
        $ref->setAccessible(true);

        $streamingServer = $ref->getValue($http);

        $ref = new \ReflectionProperty($streamingServer, 'callback');
        $ref->setAccessible(true);

        $middlewareRunner = $ref->getValue($streamingServer);

        $ref = new \ReflectionProperty($middlewareRunner, 'middleware');
        $ref->setAccessible(true);

        $middleware = $ref->getValue($middlewareRunner);

        $this->assertTrue(is_array($middleware));
        $this->assertInstanceOf('React\Http\Middleware\LimitConcurrentRequestsMiddleware', $middleware[0]);
    }

    public function testConstructFiltersOutConfigurationMiddlewareBefore()
    {
        $http = new HttpServer(new InactiveConnectionTimeoutMiddleware(0), new StreamingRequestMiddleware(), function () { });

        $ref = new \ReflectionProperty($http, 'streamingServer');
        $ref->setAccessible(true);

        $streamingServer = $ref->getValue($http);

        $ref = new \ReflectionProperty($streamingServer, 'callback');
        $ref->setAccessible(true);

        $middlewareRunner = $ref->getValue($streamingServer);

        $ref = new \ReflectionProperty($middlewareRunner, 'middleware');
        $ref->setAccessible(true);

        $middleware = $ref->getValue($middlewareRunner);

        $this->assertTrue(is_array($middleware));
        $this->assertCount(1, $middleware);
    }
}
