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

    public function testUriSimple()
    {
        $uri = $this->messageFactory->uri('http://www.lueck.tv/');

        $this->assertEquals('http', $uri->getScheme());
        $this->assertEquals('www.lueck.tv', $uri->getHost());
        $this->assertEquals('/', $uri->getPath());

        $this->assertEquals(null, $uri->getPort());
        $this->assertEquals('', $uri->getQuery());
    }

    public function testUriComplete()
    {
        $uri = $this->messageFactory->uri('https://example.com:8080/?just=testing');

        $this->assertEquals('https', $uri->getScheme());
        $this->assertEquals('example.com', $uri->getHost());
        $this->assertEquals(8080, $uri->getPort());
        $this->assertEquals('/', $uri->getPath());
        $this->assertEquals('just=testing', $uri->getQuery());
    }

    public function testPlaceholdersInUriWillBeEscaped()
    {
        $uri = $this->messageFactory->uri('http://example.com/{version}');

        $this->assertEquals('/%7Bversion%7D', $uri->getPath());
    }

    public function testEscapedPlaceholdersInUriWillStayEscaped()
    {
        $uri = $this->messageFactory->uri('http://example.com/%7Bversion%7D');

        $this->assertEquals('/%7Bversion%7D', $uri->getPath());
    }

    public function testResolveRelative()
    {
        $base = $this->messageFactory->uri('http://example.com/base/');

        $this->assertEquals('http://example.com/base/', $this->messageFactory->uriRelative($base, ''));
        $this->assertEquals('http://example.com/', $this->messageFactory->uriRelative($base, '/'));

        $this->assertEquals('http://example.com/base/a', $this->messageFactory->uriRelative($base, 'a'));
        $this->assertEquals('http://example.com/a', $this->messageFactory->uriRelative($base, '../a'));
    }

    public function testResolveAbsolute()
    {
        $base = $this->messageFactory->uri('http://example.org/');

        $this->assertEquals('http://www.example.com/', $this->messageFactory->uriRelative($base, 'http://www.example.com/'));
    }

    public function testResolveUri()
    {
        $base = $this->messageFactory->uri('http://example.org/');

        $this->assertEquals('http://www.example.com/', $this->messageFactory->uriRelative($base, $this->messageFactory->uri('http://www.example.com/')));
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
