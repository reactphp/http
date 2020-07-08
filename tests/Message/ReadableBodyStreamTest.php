<?php

namespace React\Tests\Http\Message;

use React\Http\Message\ReadableBodyStream;
use React\Tests\Http\TestCase;
use React\Stream\ThroughStream;

class ReadableBodyStreamTest extends TestCase
{
    private $input;
    private $stream;

    /**
     * @before
     */
    public function setUpStream()
    {
        $this->input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $this->stream = new ReadableBodyStream($this->input);
    }

    public function testIsReadableIfInputIsReadable()
    {
        $this->input->expects($this->once())->method('isReadable')->willReturn(true);

        $this->assertTrue($this->stream->isReadable());
    }

    public function testIsEofIfInputIsNotReadable()
    {
        $this->input->expects($this->once())->method('isReadable')->willReturn(false);

        $this->assertTrue($this->stream->eof());
    }

    public function testCloseWillCloseInputStream()
    {
        $this->input->expects($this->once())->method('close');

        $this->stream->close();
    }

    public function testCloseWillEmitCloseEvent()
    {
        $this->input = new ThroughStream();
        $this->stream = new ReadableBodyStream($this->input);

        $called = 0;
        $this->stream->on('close', function () use (&$called) {
            ++$called;
        });

        $this->stream->close();
        $this->stream->close();

        $this->assertEquals(1, $called);
    }

    public function testCloseInputWillEmitCloseEvent()
    {
        $this->input = new ThroughStream();
        $this->stream = new ReadableBodyStream($this->input);

        $called = 0;
        $this->stream->on('close', function () use (&$called) {
            ++$called;
        });

        $this->input->close();
        $this->input->close();

        $this->assertEquals(1, $called);
    }

    public function testEndInputWillEmitCloseEvent()
    {
        $this->input = new ThroughStream();
        $this->stream = new ReadableBodyStream($this->input);

        $called = 0;
        $this->stream->on('close', function () use (&$called) {
            ++$called;
        });

        $this->input->end();
        $this->input->end();

        $this->assertEquals(1, $called);
    }

    public function testEndInputWillEmitErrorEventWhenDataDoesNotReachExpectedLength()
    {
        $this->input = new ThroughStream();
        $this->stream = new ReadableBodyStream($this->input, 5);

        $called = null;
        $this->stream->on('error', function ($e) use (&$called) {
            $called = $e;
        });

        $this->input->write('hi');
        $this->input->end();

        $this->assertInstanceOf('UnderflowException', $called);
        $this->assertSame('Unexpected end of response body after 2/5 bytes', $called->getMessage());
    }

    public function testDataEventOnInputWillEmitDataEvent()
    {
        $this->input = new ThroughStream();
        $this->stream = new ReadableBodyStream($this->input);

        $called = null;
        $this->stream->on('data', function ($data) use (&$called) {
            $called = $data;
        });

        $this->input->write('hello');

        $this->assertEquals('hello', $called);
    }

    public function testDataEventOnInputWillEmitEndWhenDataReachesExpectedLength()
    {
        $this->input = new ThroughStream();
        $this->stream = new ReadableBodyStream($this->input, 5);

        $called = null;
        $this->stream->on('end', function () use (&$called) {
            ++$called;
        });

        $this->input->write('hello');

        $this->assertEquals(1, $called);
    }

    public function testEndEventOnInputWillEmitEndOnlyOnceWhenDataAlreadyReachedExpectedLength()
    {
        $this->input = new ThroughStream();
        $this->stream = new ReadableBodyStream($this->input, 5);

        $called = null;
        $this->stream->on('end', function () use (&$called) {
            ++$called;
        });

        $this->input->write('hello');
        $this->input->end();

        $this->assertEquals(1, $called);
    }

    public function testDataEventOnInputWillNotEmitEndWhenDataDoesNotReachExpectedLength()
    {
        $this->input = new ThroughStream();
        $this->stream = new ReadableBodyStream($this->input, 5);

        $called = null;
        $this->stream->on('end', function () use (&$called) {
            ++$called;
        });

        $this->input->write('hi');

        $this->assertNull($called);
    }

    public function testPauseWillPauseInputStream()
    {
        $this->input->expects($this->once())->method('pause');

        $this->stream->pause();
    }

    public function testResumeWillResumeInputStream()
    {
        $this->input->expects($this->once())->method('resume');

        $this->stream->resume();
    }

    public function testPointlessTostringReturnsEmptyString()
    {
        $this->assertEquals('', (string)$this->stream);
    }

    public function testPointlessDetachThrows()
    {
        $this->setExpectedException('BadMethodCallException');
        $this->stream->detach();
    }

    public function testPointlessGetSizeReturnsNull()
    {
        $this->assertEquals(null, $this->stream->getSize());
    }

    public function testPointlessTellThrows()
    {
        $this->setExpectedException('BadMethodCallException');
        $this->stream->tell();
    }

    public function testPointlessIsSeekableReturnsFalse()
    {
        $this->assertEquals(false, $this->stream->isSeekable());
    }

    public function testPointlessSeekThrows()
    {
        $this->setExpectedException('BadMethodCallException');
        $this->stream->seek(0);
    }

    public function testPointlessRewindThrows()
    {
        $this->setExpectedException('BadMethodCallException');
        $this->stream->rewind();
    }

    public function testPointlessIsWritableReturnsFalse()
    {
        $this->assertEquals(false, $this->stream->isWritable());
    }

    public function testPointlessWriteThrows()
    {
        $this->setExpectedException('BadMethodCallException');
        $this->stream->write('');
    }

    public function testPointlessReadThrows()
    {
        $this->setExpectedException('BadMethodCallException');
        $this->stream->read(8192);
    }

    public function testPointlessGetContentsThrows()
    {
        $this->setExpectedException('BadMethodCallException');
        $this->stream->getContents();
    }

    public function testPointlessGetMetadataReturnsNullWhenKeyIsGiven()
    {
        $this->assertEquals(null, $this->stream->getMetadata('unknown'));
    }

    public function testPointlessGetMetadataReturnsEmptyArrayWhenNoKeyIsGiven()
    {
        $this->assertEquals(array(), $this->stream->getMetadata());
    }
}
