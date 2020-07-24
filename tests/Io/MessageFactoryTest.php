<?php

namespace React\Tests\Http\Io;

use React\Http\Io\MessageFactory;
use PHPUnit\Framework\TestCase;

class MessageFactoryTest extends TestCase
{
    private $messageFactory;

    /**
     * @before
     */
    public function setUpMessageFactory()
    {
        $this->messageFactory = new MessageFactory();
    }

    public function testBodyString()
    {
        $body = $this->messageFactory->body('hi');

        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertNotInstanceOf('React\Stream\ReadableStreamInterface', $body);
        $this->assertEquals(2, $body->getSize());
        $this->assertEquals('hi', (string)$body);
    }

    public function testBodyReadableStream()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $body = $this->messageFactory->body($stream);

        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);
        $this->assertEquals(null, $body->getSize());
        $this->assertEquals('', (string)$body);
    }

    public function testResponseWithBodyString()
    {
        $response = $this->messageFactory->response('1.1', 200, 'OK', array(), 'hi');

        $body = $response->getBody();
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertNotInstanceOf('React\Stream\ReadableStreamInterface', $body);
        $this->assertEquals(2, $body->getSize());
        $this->assertEquals('hi', (string)$body);
    }

    public function testResponseWithStreamingBodyHasUnknownSizeByDefault()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $response = $this->messageFactory->response('1.1', 200, 'OK', array(), $stream);

        $body = $response->getBody();
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);
        $this->assertNull($body->getSize());
        $this->assertEquals('', (string)$body);
    }

    public function testResponseWithStreamingBodyHasSizeFromContentLengthHeader()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $response = $this->messageFactory->response('1.1', 200, 'OK', array('Content-Length' => '100'), $stream);

        $body = $response->getBody();
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);
        $this->assertEquals(100, $body->getSize());
        $this->assertEquals('', (string)$body);
    }

    public function testResponseWithStreamingBodyHasUnknownSizeWithTransferEncodingChunkedHeader()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $response = $this->messageFactory->response('1.1', 200, 'OK', array('Transfer-Encoding' => 'chunked'), $stream);

        $body = $response->getBody();
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);
        $this->assertNull($body->getSize());
        $this->assertEquals('', (string)$body);
    }

    public function testResponseWithStreamingBodyHasZeroSizeForInformationalResponse()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $response = $this->messageFactory->response('1.1', 101, 'OK', array('Content-Length' => '100'), $stream);

        $body = $response->getBody();
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);
        $this->assertEquals(0, $body->getSize());
        $this->assertEquals('', (string)$body);
    }

    public function testResponseWithStreamingBodyHasZeroSizeForNoContentResponse()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $response = $this->messageFactory->response('1.1', 204, 'OK', array('Content-Length' => '100'), $stream);

        $body = $response->getBody();
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);
        $this->assertEquals(0, $body->getSize());
        $this->assertEquals('', (string)$body);
    }

    public function testResponseWithStreamingBodyHasZeroSizeForNotModifiedResponse()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $response = $this->messageFactory->response('1.1', 304, 'OK', array('Content-Length' => '100'), $stream);

        $body = $response->getBody();
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);
        $this->assertEquals(0, $body->getSize());
        $this->assertEquals('', (string)$body);
    }

    public function testResponseWithStreamingBodyHasZeroSizeForHeadRequestMethod()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $response = $this->messageFactory->response('1.1', 200, 'OK', array('Content-Length' => '100'), $stream, 'HEAD');

        $body = $response->getBody();
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);
        $this->assertEquals(0, $body->getSize());
        $this->assertEquals('', (string)$body);
    }
}
