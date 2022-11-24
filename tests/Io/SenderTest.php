<?php

namespace React\Tests\Http\Io;

use React\Http\Client\Client as HttpClient;
use React\Http\Client\RequestData;
use React\Http\Io\ReadableBodyStream;
use React\Http\Io\Sender;
use React\Http\Message\Request;
use React\Promise;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;

class SenderTest extends TestCase
{
    private $loop;

    /**
     * @before
     */
    public function setUpLoop()
    {
        $this->loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
    }

    public function testCreateFromLoop()
    {
        $sender = Sender::createFromLoop($this->loop, null);

        $this->assertInstanceOf('React\Http\Io\Sender', $sender);
    }

    public function testSenderRejectsInvalidUri()
    {
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->never())->method('connect');

        $sender = new Sender(new HttpClient($this->loop, $connector));

        $request = new Request('GET', 'www.google.com');

        $promise = $sender->send($request);

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertInstanceOf('InvalidArgumentException', $exception);
    }

    public function testSenderConnectorRejection()
    {
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->willReturn(Promise\reject(new \RuntimeException('Rejected')));

        $sender = new Sender(new HttpClient($this->loop, $connector));

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request);

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertInstanceOf('RuntimeException', $exception);
    }

    public function testSendPostWillAutomaticallySendContentLengthHeader()
    {
        $client = $this->getMockBuilder('React\Http\Client\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->with(
            'POST',
            'http://www.google.com/',
            array('Host' => 'www.google.com', 'Content-Length' => '5'),
            '1.1'
        )->willReturn($this->getMockBuilder('React\Http\Client\Request')->disableOriginalConstructor()->getMock());

        $sender = new Sender($client);

        $request = new Request('POST', 'http://www.google.com/', array(), 'hello');
        $sender->send($request);
    }

    public function testSendPostWillAutomaticallySendContentLengthZeroHeaderForEmptyRequestBody()
    {
        $client = $this->getMockBuilder('React\Http\Client\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->with(
            'POST',
            'http://www.google.com/',
            array('Host' => 'www.google.com', 'Content-Length' => '0'),
            '1.1'
        )->willReturn($this->getMockBuilder('React\Http\Client\Request')->disableOriginalConstructor()->getMock());

        $sender = new Sender($client);

        $request = new Request('POST', 'http://www.google.com/', array(), '');
        $sender->send($request);
    }

    public function testSendPostStreamWillAutomaticallySendTransferEncodingChunked()
    {
        $outgoing = $this->getMockBuilder('React\Http\Client\Request')->disableOriginalConstructor()->getMock();
        $outgoing->expects($this->once())->method('write')->with("");

        $client = $this->getMockBuilder('React\Http\Client\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->with(
            'POST',
            'http://www.google.com/',
            array('Host' => 'www.google.com', 'Transfer-Encoding' => 'chunked'),
            '1.1'
        )->willReturn($outgoing);

        $sender = new Sender($client);

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://www.google.com/', array(), new ReadableBodyStream($stream));
        $sender->send($request);
    }

    public function testSendPostStreamWillAutomaticallyPipeChunkEncodeBodyForWriteAndRespectRequestThrottling()
    {
        $outgoing = $this->getMockBuilder('React\Http\Client\Request')->disableOriginalConstructor()->getMock();
        $outgoing->expects($this->once())->method('isWritable')->willReturn(true);
        $outgoing->expects($this->exactly(2))->method('write')->withConsecutive(array(""), array("5\r\nhello\r\n"))->willReturn(false);

        $client = $this->getMockBuilder('React\Http\Client\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->willReturn($outgoing);

        $sender = new Sender($client);

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://www.google.com/', array(), new ReadableBodyStream($stream));
        $sender->send($request);

        $ret = $stream->write('hello');
        $this->assertFalse($ret);
    }

    public function testSendPostStreamWillAutomaticallyPipeChunkEncodeBodyForEnd()
    {
        $outgoing = $this->getMockBuilder('React\Http\Client\Request')->disableOriginalConstructor()->getMock();
        $outgoing->expects($this->once())->method('isWritable')->willReturn(true);
        $outgoing->expects($this->exactly(2))->method('write')->withConsecutive(array(""), array("0\r\n\r\n"))->willReturn(false);
        $outgoing->expects($this->once())->method('end')->with(null);

        $client = $this->getMockBuilder('React\Http\Client\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->willReturn($outgoing);

        $sender = new Sender($client);

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://www.google.com/', array(), new ReadableBodyStream($stream));
        $sender->send($request);

        $stream->end();
    }

    public function testSendPostStreamWillRejectWhenRequestBodyEmitsErrorEvent()
    {
        $outgoing = $this->getMockBuilder('React\Http\Client\Request')->disableOriginalConstructor()->getMock();
        $outgoing->expects($this->once())->method('isWritable')->willReturn(true);
        $outgoing->expects($this->once())->method('write')->with("")->willReturn(false);
        $outgoing->expects($this->never())->method('end');
        $outgoing->expects($this->once())->method('close');

        $client = $this->getMockBuilder('React\Http\Client\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->willReturn($outgoing);

        $sender = new Sender($client);

        $expected = new \RuntimeException();
        $stream = new ThroughStream();
        $request = new Request('POST', 'http://www.google.com/', array(), new ReadableBodyStream($stream));
        $promise = $sender->send($request);

        $stream->emit('error', array($expected));

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertInstanceOf('RuntimeException', $exception);
        $this->assertEquals('Request failed because request body reported an error', $exception->getMessage());
        $this->assertSame($expected, $exception->getPrevious());
    }

    public function testSendPostStreamWillRejectWhenRequestBodyClosesWithoutEnd()
    {
        $outgoing = $this->getMockBuilder('React\Http\Client\Request')->disableOriginalConstructor()->getMock();
        $outgoing->expects($this->once())->method('isWritable')->willReturn(true);
        $outgoing->expects($this->once())->method('write')->with("")->willReturn(false);
        $outgoing->expects($this->never())->method('end');
        $outgoing->expects($this->once())->method('close');

        $client = $this->getMockBuilder('React\Http\Client\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->willReturn($outgoing);

        $sender = new Sender($client);

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://www.google.com/', array(), new ReadableBodyStream($stream));
        $promise = $sender->send($request);

        $stream->close();

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertInstanceOf('RuntimeException', $exception);
        $this->assertEquals('Request failed because request body closed unexpectedly', $exception->getMessage());
    }

    public function testSendPostStreamWillNotRejectWhenRequestBodyClosesAfterEnd()
    {
        $outgoing = $this->getMockBuilder('React\Http\Client\Request')->disableOriginalConstructor()->getMock();
        $outgoing->expects($this->once())->method('isWritable')->willReturn(true);
        $outgoing->expects($this->exactly(2))->method('write')->withConsecutive(array(""), array("0\r\n\r\n"))->willReturn(false);
        $outgoing->expects($this->once())->method('end');
        $outgoing->expects($this->never())->method('close');

        $client = $this->getMockBuilder('React\Http\Client\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->willReturn($outgoing);

        $sender = new Sender($client);

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://www.google.com/', array(), new ReadableBodyStream($stream));
        $promise = $sender->send($request);

        $stream->end();
        $stream->close();

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertNull($exception);
    }

    public function testSendPostStreamWithExplicitContentLengthWillSendHeaderAsIs()
    {
        $client = $this->getMockBuilder('React\Http\Client\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->with(
            'POST',
            'http://www.google.com/',
            array('Host' => 'www.google.com', 'Content-Length' => '100'),
            '1.1'
        )->willReturn($this->getMockBuilder('React\Http\Client\Request')->disableOriginalConstructor()->getMock());

        $sender = new Sender($client);

        $stream = new ThroughStream();
        $request = new Request('POST', 'http://www.google.com/', array('Content-Length' => '100'), new ReadableBodyStream($stream));
        $sender->send($request);
    }

    public function testSendGetWillNotPassContentLengthHeaderForEmptyRequestBody()
    {
        $client = $this->getMockBuilder('React\Http\Client\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->with(
            'GET',
            'http://www.google.com/',
            array('Host' => 'www.google.com'),
            '1.1'
        )->willReturn($this->getMockBuilder('React\Http\Client\Request')->disableOriginalConstructor()->getMock());

        $sender = new Sender($client);

        $request = new Request('GET', 'http://www.google.com/');
        $sender->send($request);
    }

    public function testSendCustomMethodWillNotPassContentLengthHeaderForEmptyRequestBody()
    {
        $client = $this->getMockBuilder('React\Http\Client\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->with(
            'CUSTOM',
            'http://www.google.com/',
            array('Host' => 'www.google.com'),
            '1.1'
        )->willReturn($this->getMockBuilder('React\Http\Client\Request')->disableOriginalConstructor()->getMock());

        $sender = new Sender($client);

        $request = new Request('CUSTOM', 'http://www.google.com/');
        $sender->send($request);
    }

    public function testSendCustomMethodWithExplicitContentLengthZeroWillBePassedAsIs()
    {
        $client = $this->getMockBuilder('React\Http\Client\Client')->disableOriginalConstructor()->getMock();
        $client->expects($this->once())->method('request')->with(
            'CUSTOM',
            'http://www.google.com/',
            array('Host' => 'www.google.com', 'Content-Length' => '0'),
            '1.1'
        )->willReturn($this->getMockBuilder('React\Http\Client\Request')->disableOriginalConstructor()->getMock());

        $sender = new Sender($client);

        $request = new Request('CUSTOM', 'http://www.google.com/', array('Content-Length' => '0'));
        $sender->send($request);
    }

    public function testCancelRequestWillCancelConnector()
    {
        $promise = new \React\Promise\Promise(function () { }, function () {
            throw new \RuntimeException();
        });

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->willReturn($promise);

        $sender = new Sender(new HttpClient($this->loop, $connector));

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request);
        $promise->cancel();

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertInstanceOf('RuntimeException', $exception);
    }

    public function testCancelRequestWillCloseConnection()
    {
        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();
        $connection->expects($this->once())->method('close');

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->willReturn(Promise\resolve($connection));

        $sender = new Sender(new HttpClient($this->loop, $connector));

        $request = new Request('GET', 'http://www.google.com/');

        $promise = $sender->send($request);
        $promise->cancel();

        $exception = null;
        $promise->then(null, function ($e) use (&$exception) {
            $exception = $e;
        });

        $this->assertInstanceOf('RuntimeException', $exception);
    }

    public function provideRequestProtocolVersion()
    {
        return array(
            array(
                new Request('GET', 'http://www.google.com/'),
                'GET',
                'http://www.google.com/',
                array(
                    'Host' => 'www.google.com',
                ),
                '1.1',
            ),
            array(
                new Request('GET', 'http://www.google.com/', array(), '', '1.0'),
                'GET',
                'http://www.google.com/',
                array(
                    'Host' => 'www.google.com',
                ),
                '1.0',
            ),
        );
    }

    /**
     * @dataProvider provideRequestProtocolVersion
     */
    public function testRequestProtocolVersion(Request $Request, $method, $uri, $headers, $protocolVersion)
    {
        $http = $this->getMockBuilder('React\Http\Client\Client')
                    ->setMethods(array(
                        'request',
                    ))
                    ->setConstructorArgs(array(
                        $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock(),
                    ))->getMock();

        $request = $this->getMockBuilder('React\Http\Client\Request')
                        ->setMethods(array())
                        ->setConstructorArgs(array(
                            $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock(),
                            new RequestData($method, $uri, $headers, $protocolVersion),
                        ))->getMock();

        $http->expects($this->once())->method('request')->with($method, $uri, $headers, $protocolVersion)->willReturn($request);

        $sender = new Sender($http);
        $sender->send($Request);
    }
}
