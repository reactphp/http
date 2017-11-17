<?php

namespace React\Tests\Http\Io;

use React\Http\Io\LengthLimitedStream;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;

class LengthLimitedStreamTest extends TestCase
{
    private $input;
    private $stream;

    public function setUp()
    {
        $this->input = new ThroughStream();
    }

    public function testSimpleChunk()
    {
        $stream = new LengthLimitedStream($this->input, 5);
        $stream->on('data', $this->expectCallableOnceWith('hello'));
        $stream->on('end', $this->expectCallableOnce());
        $this->input->emit('data', array("hello world"));
    }

    public function testInputStreamKeepsEmitting()
    {
        $stream = new LengthLimitedStream($this->input, 5);
        $stream->on('data', $this->expectCallableOnceWith('hello'));
        $stream->on('end', $this->expectCallableOnce());

        $this->input->emit('data', array("hello world"));
        $this->input->emit('data', array("world"));
        $this->input->emit('data', array("world"));
    }

    public function testZeroLengthInContentLengthWillIgnoreEmittedDataEvents()
    {
        $stream = new LengthLimitedStream($this->input, 0);
        $stream->on('data', $this->expectCallableNever());
        $stream->on('end', $this->expectCallableOnce());
        $this->input->emit('data', array("hello world"));
    }

    public function testHandleError()
    {
        $stream = new LengthLimitedStream($this->input, 0);
        $stream->on('error', $this->expectCallableOnce());
        $stream->on('close', $this->expectCallableOnce());

        $this->input->emit('error', array(new \RuntimeException()));

        $this->assertFalse($stream->isReadable());
    }

    public function testPauseStream()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('pause');

        $stream = new LengthLimitedStream($input, 0);
        $stream->pause();
    }

    public function testResumeStream()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('pause');

        $stream = new LengthLimitedStream($input, 0);
        $stream->pause();
        $stream->resume();
    }

    public function testPipeStream()
    {
        $stream = new LengthLimitedStream($this->input, 0);
        $dest = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $ret = $stream->pipe($dest);

        $this->assertSame($dest, $ret);
    }

    public function testHandleClose()
    {
        $stream = new LengthLimitedStream($this->input, 0);
        $stream->on('close', $this->expectCallableOnce());

        $this->input->close();
        $this->input->emit('end', array());

        $this->assertFalse($stream->isReadable());
    }

    public function testOutputStreamCanCloseInputStream()
    {
        $input = new ThroughStream();
        $input->on('close', $this->expectCallableOnce());

        $stream = new LengthLimitedStream($input, 0);
        $stream->on('close', $this->expectCallableOnce());

        $stream->close();

        $this->assertFalse($input->isReadable());
    }

    public function testHandleUnexpectedEnd()
    {
        $stream = new LengthLimitedStream($this->input, 5);

        $stream->on('data', $this->expectCallableNever());
        $stream->on('close', $this->expectCallableOnce());
        $stream->on('end', $this->expectCallableNever());
        $stream->on('error', $this->expectCallableOnce());

        $this->input->emit('end');
    }
}
