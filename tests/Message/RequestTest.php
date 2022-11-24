<?php

namespace React\Tests\Http\Message;

use React\Http\Io\HttpBodyStream;
use React\Http\Message\Request;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;

class RequestTest extends TestCase
{
    public function testConstructWithStringRequestBodyReturnsStringBodyWithAutomaticSize()
    {
        $request = new Request(
            'GET',
            'http://localhost',
            array(),
            'foo'
        );

        $body = $request->getBody();
        $this->assertSame(3, $body->getSize());
        $this->assertEquals('foo', (string) $body);
    }

    public function testConstructWithStreamingRequestBodyReturnsBodyWhichImplementsReadableStreamInterfaceWithUnknownSize()
    {
        $request = new Request(
            'GET',
            'http://localhost',
            array(),
            new ThroughStream()
        );

        $body = $request->getBody();
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $body);
        $this->assertNull($body->getSize());
    }

    public function testConstructWithHttpBodyStreamReturnsBodyAsIs()
    {
        $request = new Request(
            'GET',
            'http://localhost',
            array(),
            $body = new HttpBodyStream(new ThroughStream(), 100)
        );

        $this->assertSame($body, $request->getBody());
    }

    public function testConstructWithNullBodyThrows()
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid request body given');
        new Request(
            'GET',
            'http://localhost',
            array(),
            null
        );
    }
}
