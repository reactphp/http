<?php

namespace React\Tests\Io;

use React\Tests\Http\TestCase;
use React\Stream\ThroughStream;
use React\Http\Io\PauseBufferStream;

class PauseBufferStreamTest extends TestCase
{
    public function testPauseMethodWillBePassedThroughToInput()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('pause');

        $stream = new PauseBufferStream($input);
        $stream->pause();
    }

    public function testCloseMethodWillBePassedThroughToInput()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('close');

        $stream = new PauseBufferStream($input);
        $stream->close();
    }

    public function testPauseMethodWillNotBePassedThroughToInputAfterClose()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->never())->method('pause');

        $stream = new PauseBufferStream($input);
        $stream->close();
        $stream->pause();
    }

    public function testDataEventWillBePassedThroughAsIs()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $stream->on('data', $this->expectCallableOnceWith('hello'));
        $input->write('hello');
    }

    public function testDataEventWillBePipedThroughAsIs()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $output = new ThroughStream($this->expectCallableOnceWith('hello'));
        $stream->pipe($output);

        $input->write('hello');
    }

    public function testPausedStreamWillNotPassThroughDataEvent()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $stream->pause();
        $stream->on('data', $this->expectCallableNever());
        $input->write('hello');
    }

    public function testPauseStreamWillNotPipeThroughDataEvent()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $output = new ThroughStream($this->expectCallableNever());
        $stream->pipe($output);

        $stream->pause();
        $input->write('hello');
    }

    public function testPausedStreamWillPassThroughDataEventOnResume()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $stream->pause();
        $input->write('hello');

        $stream->on('data', $this->expectCallableOnceWith('hello'));
        $stream->resume();
    }

    public function testEndEventWillBePassedThroughAsIs()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $stream->on('data', $this->expectCallableOnceWith('hello'));
        $stream->on('end', $this->expectCallableOnce());
        $stream->on('close', $this->expectCallableOnce());
        $input->end('hello');

        $this->assertFalse($stream->isReadable());
    }

    public function testPausedStreamWillNotPassThroughEndEvent()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $stream->pause();
        $stream->on('data', $this->expectCallableNever());
        $stream->on('end', $this->expectCallableNever());
        $stream->on('close', $this->expectCallableNever());
        $input->end('hello');

        $this->assertTrue($stream->isReadable());
    }

    public function testPausedStreamWillPassThroughEndEventOnResume()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $stream->pause();
        $input->end('hello');

        $stream->on('data', $this->expectCallableOnceWith('hello'));
        $stream->on('end', $this->expectCallableOnce());
        $stream->on('close', $this->expectCallableOnce());
        $stream->resume();

        $this->assertFalse($stream->isReadable());
    }

    public function testPausedStreamWillNotPassThroughEndEventOnExplicitClose()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $stream->pause();
        $stream->on('data', $this->expectCallableNever());
        $stream->on('end', $this->expectCallableNever());
        $stream->on('close', $this->expectCallableOnce());
        $input->end('hello');

        $stream->close();

        $this->assertFalse($stream->isReadable());
    }

    public function testErrorEventWillBePassedThroughAsIs()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $stream->on('error', $this->expectCallableOnce());
        $stream->on('close', $this->expectCallableOnce());
        $input->emit('error', array(new \RuntimeException()));
    }

    public function testPausedStreamWillNotPassThroughErrorEvent()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $stream->pause();
        $stream->on('error', $this->expectCallableNever());
        $stream->on('close', $this->expectCallableNever());
        $input->emit('error', array(new \RuntimeException()));
    }

    public function testPausedStreamWillPassThroughErrorEventOnResume()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $stream->pause();
        $input->emit('error', array(new \RuntimeException()));

        $stream->on('error', $this->expectCallableOnce());
        $stream->on('close', $this->expectCallableOnce());
        $stream->resume();
    }

    public function testPausedStreamWillNotPassThroughErrorEventOnExplicitClose()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $stream->pause();
        $stream->on('error', $this->expectCallableNever());
        $stream->on('close', $this->expectCallableOnce());
        $input->emit('error', array(new \RuntimeException()));

        $stream->close();
    }

    public function testCloseEventWillBePassedThroughAsIs()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $stream->on('data', $this->expectCallableNever());
        $stream->on('end', $this->expectCallableNever());
        $stream->on('close', $this->expectCallableOnce());
        $input->close();
    }

    public function testPausedStreamWillNotPassThroughCloseEvent()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $stream->pause();
        $stream->on('data', $this->expectCallableNever());
        $stream->on('end', $this->expectCallableNever());
        $stream->on('close', $this->expectCallableNever());
        $input->close();
    }

    public function testPausedStreamWillPassThroughCloseEventOnResume()
    {
        $input = new ThroughStream();
        $stream = new PauseBufferStream($input);

        $stream->pause();
        $input->close();

        $stream->on('data', $this->expectCallableNever());
        $stream->on('end', $this->expectCallableNever());
        $stream->on('close', $this->expectCallableOnce());
        $stream->resume();
    }
}
