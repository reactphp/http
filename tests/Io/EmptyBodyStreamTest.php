<?php

namespace React\Tests\Http\Io;

use React\Http\Io\EmptyBodyStream;
use React\Stream\WritableStreamInterface;
use React\Tests\Http\TestCase;

class EmptyBodyStreamTest extends TestCase
{
    private $input;
    private $bodyStream;

    /**
     * @before
     */
    public function setUpBodyStream()
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
        $dest = $this->createMock(WritableStreamInterface::class);

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

        $this->assertEquals([], $this->bodyStream->listeners('close'));
    }

    public function testTell()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->bodyStream->tell();
    }

    public function testEof()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->bodyStream->eof();
    }

    public function testIsSeekable()
    {
        $this->assertFalse($this->bodyStream->isSeekable());
    }

    public function testWrite()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->bodyStream->write('');
    }

    public function testRead()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->bodyStream->read(1);
    }

    public function testGetContentsReturnsEmpy()
    {
        $this->assertEquals('', $this->bodyStream->getContents());
    }

    public function testGetMetaDataWithoutKeyReturnsEmptyArray()
    {
        $this->assertSame([], $this->bodyStream->getMetadata());
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

    public function testSeek()
    {
        $reflectionMethod = new \ReflectionMethod('\Psr\Http\Message\StreamInterface', 'eof');
        if (PHP_VERSION_ID < 70000 || !$reflectionMethod->hasReturnType()) {
            $this->expectException(\BadMethodCallException::class);
        } else {
            $this->expectException(\TypeError::class);
        }

        $this->bodyStream->seek('');
    }

    public function testRewind()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->bodyStream->rewind();
    }

    public function testIsWriteable()
    {
        $this->assertFalse($this->bodyStream->isWritable());
    }
}
