<?php

namespace React\Tests\Http\Io;

use React\Http\Io\EmptyBodyStream;
use React\Tests\Http\TestCase;

class EmptyBodyStreamTest extends TestCase
{
    private $input;
    private $bodyStream;

    public function setUp()
    {
        $this->bodyStream = new EmptyBodyStream();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testPauseIsNoop()
    {
        $this->bodyStream->pause();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testResumeIsNoop()
    {
        $this->bodyStream->resume();
    }

    public function testPipeStreamReturnsDestinationStream()
    {
        $dest = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $ret = $this->bodyStream->pipe($dest);

        $this->assertSame($dest, $ret);
    }

    public function testToStringReturnsEmptyString()
    {
        $this->assertEquals('', $this->bodyStream->__toString());
    }

    public function testDetachReturnsNull()
    {
        $this->assertNull($this->bodyStream->detach());
    }

    public function testGetSizeReturnsZero()
    {
        $this->assertSame(0, $this->bodyStream->getSize());
    }

    public function testCloseTwiceEmitsCloseEventAndClearsListeners()
    {
        $this->bodyStream->on('close', $this->expectCallableOnce());

        $this->bodyStream->close();
        $this->bodyStream->close();

        $this->assertEquals(array(), $this->bodyStream->listeners('close'));
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testTell()
    {
        $this->bodyStream->tell();
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testEof()
    {
        $this->bodyStream->eof();
    }

    public function testIsSeekable()
    {
        $this->assertFalse($this->bodyStream->isSeekable());
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testWrite()
    {
        $this->bodyStream->write('');
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testRead()
    {
        $this->bodyStream->read(1);
    }

    public function testGetContentsReturnsEmpy()
    {
        $this->assertEquals('', $this->bodyStream->getContents());
    }

    public function testGetMetaDataWithoutKeyReturnsEmptyArray()
    {
        $this->assertSame(array(), $this->bodyStream->getMetadata());
    }

    public function testGetMetaDataWithKeyReturnsNull()
    {
        $this->assertNull($this->bodyStream->getMetadata('anything'));
    }

    public function testIsReadableReturnsTrueWhenNotClosed()
    {
        $this->assertTrue($this->bodyStream->isReadable());
    }

    public function testIsReadableReturnsFalseWhenAlreadyClosed()
    {
        $this->bodyStream->close();

        $this->assertFalse($this->bodyStream->isReadable());
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testSeek()
    {
        $this->bodyStream->seek('');
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testRewind()
    {
        $this->bodyStream->rewind();
    }

    public function testIsWriteable()
    {
        $this->assertFalse($this->bodyStream->isWritable());
    }
}
