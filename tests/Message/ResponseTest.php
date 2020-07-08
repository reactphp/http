<?php

namespace React\Tests\Http\Message;

use React\Http\Io\HttpBodyStream;
use React\Http\Message\Response;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;

class ResponseTest extends TestCase
{

    public function testStringBodyWillBePsr7Stream()
    {
        $response = new Response(200, array(), 'hello');
        $this->assertInstanceOf('RingCentral\Psr7\Stream', $response->getBody());
    }

    public function testConstructWithStreamingBodyWillReturnReadableBodyStream()
    {
        $response = new Response(200, array(), new ThroughStream());

        $body = $response->getBody();
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $body);
        $this->assertInstanceof('React\Stream\ReadableStreamInterface', $body);
        $this->assertInstanceOf('React\Http\Io\HttpBodyStream', $body);
        $this->assertNull($body->getSize());
    }

    public function testConstructWithHttpBodyStreamReturnsBodyAsIs()
    {
        $response = new Response(
            200,
            array(),
            $body = new HttpBodyStream(new ThroughStream(), 100)
        );

        $this->assertSame($body, $response->getBody());
    }

    public function testFloatBodyWillThrow()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Response(200, array(), 1.0);
    }

    public function testResourceBodyWillThrow()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Response(200, array(), tmpfile());
    }
}
