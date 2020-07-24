<?php

namespace React\Tests\Http\Client;

use React\Http\Client\Response;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;

class ResponseTest extends TestCase
{
    private $stream;

    /**
     * @before
     */
    public function setUpStream()
    {
        $this->stream = $this->getMockBuilder('React\Stream\DuplexStreamInterface')
            ->getMock();
    }

    /** @test */
    public function responseShouldEmitEndEventOnEnd()
    {
        $this->stream
            ->expects($this->at(0))
            ->method('on')
            ->with('data', $this->anything());
        $this->stream
            ->expects($this->at(1))
            ->method('on')
            ->with('error', $this->anything());
        $this->stream
            ->expects($this->at(2))
            ->method('on')
            ->with('end', $this->anything());
        $this->stream
            ->expects($this->at(3))
            ->method('on')
            ->with('close', $this->anything());

        $response = new Response($this->stream, 'HTTP', '1.0', '200', 'OK', array('Content-Type' => 'text/plain'));

        $handler = $this->createCallableMock();
        $handler->expects($this->once())
            ->method('__invoke')
            ->with('some data');

        $response->on('data', $handler);

        $handler = $this->createCallableMock();
        $handler->expects($this->once())
            ->method('__invoke');

        $response->on('end', $handler);

        $handler = $this->createCallableMock();
        $handler->expects($this->once())
            ->method('__invoke');

        $response->on('close', $handler);

        $this->stream
            ->expects($this->at(0))
            ->method('close');

        $response->handleData('some data');
        $response->handleEnd();

        $this->assertSame(
            array(
                'Content-Type' => 'text/plain'
            ),
            $response->getHeaders()
        );
    }

    /** @test */
    public function closedResponseShouldNotBeResumedOrPaused()
    {
        $response = new Response($this->stream, 'http', '1.0', '200', 'ok', array('content-type' => 'text/plain'));

        $this->stream
            ->expects($this->never())
            ->method('pause');
        $this->stream
            ->expects($this->never())
            ->method('resume');

        $response->handleEnd();

        $response->resume();
        $response->pause();

        $this->assertSame(
            array(
                'content-type' => 'text/plain',
            ),
            $response->getHeaders()
        );
    }
}

