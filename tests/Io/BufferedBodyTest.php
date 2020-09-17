<?php

namespace React\Tests\Http\Io;

use React\Tests\Http\TestCase;
use React\Http\Io\BufferedBody;

class BufferedBodyTest extends TestCase
{
    public function testEmpty()
    {
        $stream = new BufferedBody('');

        $this->assertTrue($stream->isReadable());
        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isSeekable());
        $this->assertSame(0, $stream->getSize());
        $this->assertSame('', $stream->getContents());
        $this->assertSame('', (string) $stream);
    }

    public function testClose()
    {
        $stream = new BufferedBody('hello');
        $stream->close();

        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
        $this->assertFalse($stream->isSeekable());
        $this->assertTrue($stream->eof());
        $this->assertNull($stream->getSize());
        $this->assertSame('', (string) $stream);
    }

    public function testDetachReturnsNullAndCloses()
    {
        $stream = new BufferedBody('hello');
        $this->assertNull($stream->detach());

        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
        $this->assertFalse($stream->isSeekable());
        $this->assertTrue($stream->eof());
        $this->assertNull($stream->getSize());
        $this->assertSame('', (string) $stream);
    }

    public function testSeekAndTellPosition()
    {
        $stream = new BufferedBody('hello');

        $this->assertSame(0, $stream->tell());
        $this->assertFalse($stream->eof());

        $stream->seek(1);
        $this->assertSame(1, $stream->tell());
        $this->assertFalse($stream->eof());

        $stream->seek(2, SEEK_CUR);
        $this->assertSame(3, $stream->tell());
        $this->assertFalse($stream->eof());

        $stream->seek(-1, SEEK_END);
        $this->assertSame(4, $stream->tell());
        $this->assertFalse($stream->eof());

        $stream->seek(0, SEEK_END);
        $this->assertSame(5, $stream->tell());
        $this->assertTrue($stream->eof());
    }

    public function testSeekAfterEndIsPermitted()
    {
        $stream = new BufferedBody('hello');

        $stream->seek(1000);
        $this->assertSame(1000, $stream->tell());
        $this->assertTrue($stream->eof());

        $stream->seek(0, SEEK_END);
        $this->assertSame(5, $stream->tell());
        $this->assertTrue($stream->eof());
    }

    public function testSeekBeforeStartThrows()
    {
        $stream = new BufferedBody('hello');

        try {
            $stream->seek(-10, SEEK_CUR);
        } catch (\RuntimeException $e) {
            $this->assertSame(0, $stream->tell());

            $this->setExpectedException('RuntimeException');
            throw $e;
        }
    }

    public function testSeekWithInvalidModeThrows()
    {
        $stream = new BufferedBody('hello');

        $this->setExpectedException('InvalidArgumentException');
        $stream->seek(1, 12345);
    }

    public function testSeekAfterCloseThrows()
    {
        $stream = new BufferedBody('hello');
        $stream->close();

        $this->setExpectedException('RuntimeException');
        $stream->seek(0);
    }

    public function testTellAfterCloseThrows()
    {
        $stream = new BufferedBody('hello');
        $stream->close();

        $this->setExpectedException('RuntimeException');
        $stream->tell();
    }

    public function testRewindSeeksToStartPosition()
    {
        $stream = new BufferedBody('hello');

        $stream->seek(1);
        $stream->rewind();
        $this->assertSame(0, $stream->tell());
    }

    public function testRewindAfterCloseThrows()
    {
        $stream = new BufferedBody('hello');
        $stream->close();

        $this->setExpectedException('RuntimeException');
        $stream->rewind();
    }

    public function testGetContentsMultipleTimesReturnsBodyOnlyOnce()
    {
        $stream = new BufferedBody('hello');

        $this->assertSame(5, $stream->getSize());
        $this->assertSame('hello', $stream->getContents());
        $this->assertSame('', $stream->getContents());
    }

    public function testReadReturnsChunkAndAdvancesPosition()
    {
        $stream = new BufferedBody('hello');

        $this->assertSame('he', $stream->read(2));
        $this->assertSame(2, $stream->tell());

        $this->assertSame('ll', $stream->read(2));
        $this->assertSame(4, $stream->tell());

        $this->assertSame('o', $stream->read(2));
        $this->assertSame(5, $stream->tell());

        $this->assertSame('', $stream->read(2));
        $this->assertSame(5, $stream->tell());
    }

    public function testReadAfterEndReturnsEmptyStringWithoutChangingPosition()
    {
        $stream = new BufferedBody('hello');

        $stream->seek(1000);

        $this->assertSame('', $stream->read(2));
        $this->assertSame(1000, $stream->tell());
    }

    public function testReadZeroThrows()
    {
        $stream = new BufferedBody('hello');

        $this->setExpectedException('InvalidArgumentException');
        $stream->read(0);
    }

    public function testReadAfterCloseThrows()
    {
        $stream = new BufferedBody('hello');
        $stream->close();

        $this->setExpectedException('RuntimeException');
        $stream->read(10);
    }

    public function testGetContentsReturnsWholeBufferAndAdvancesPositionToEof()
    {
        $stream = new BufferedBody('hello');

        $this->assertSame('hello', $stream->getContents());
        $this->assertSame(5, $stream->tell());
        $this->assertTrue($stream->eof());
    }

    public function testGetContentsAfterEndsReturnsEmptyStringWithoutChangingPosition()
    {
        $stream = new BufferedBody('hello');

        $stream->seek(100);

        $this->assertSame('', $stream->getContents());
        $this->assertSame(100, $stream->tell());
        $this->assertTrue($stream->eof());
    }

    public function testGetContentsAfterCloseThrows()
    {
        $stream = new BufferedBody('hello');
        $stream->close();

        $this->setExpectedException('RuntimeException');
        $stream->getContents();
    }

    public function testWriteAdvancesPosition()
    {
        $stream = new BufferedBody('');

        $this->assertSame(2, $stream->write('he'));
        $this->assertSame(2, $stream->tell());

        $this->assertSame(2, $stream->write('ll'));
        $this->assertSame(4, $stream->tell());

        $this->assertSame(1, $stream->write('o'));
        $this->assertSame(5, $stream->tell());

        $this->assertSame(0, $stream->write(''));
        $this->assertSame(5, $stream->tell());
    }

    public function testWriteInMiddleOfBufferOverwrites()
    {
        $stream = new BufferedBody('hello');

        $stream->seek(1);
        $this->assertSame(1, $stream->write('a'));

        $this->assertSame(2, $stream->tell());
        $this->assertsame(5, $stream->getSize());
        $this->assertSame('hallo', (string) $stream);
    }

    public function testWriteOverEndOverwritesAndAppends()
    {
        $stream = new BufferedBody('hello');

        $stream->seek(4);
        $this->assertSame(2, $stream->write('au'));

        $this->assertSame(6, $stream->tell());
        $this->assertsame(6, $stream->getSize());
        $this->assertSame('hellau', (string) $stream);
    }

    public function testWriteAfterEndAppendsAndFillsWithNullBytes()
    {
        $stream = new BufferedBody('hello');

        $stream->seek(6);
        $this->assertSame(6, $stream->write('binary'));

        $this->assertSame(12, $stream->tell());
        $this->assertsame(12, $stream->getSize());
        $this->assertSame('hello' . "\0" . 'binary', (string) $stream);
    }

    public function testWriteAfterCloseThrows()
    {
        $stream = new BufferedBody('hello');
        $stream->close();

        $this->setExpectedException('RuntimeException');
        $stream->write('foo');
    }

    public function testGetMetadataWithoutKeyReturnsEmptyArray()
    {
        $stream = new BufferedBody('hello');

        $this->assertEquals(array(), $stream->getMetadata());
    }

    public function testGetMetadataWithKeyReturnsNull()
    {
        $stream = new BufferedBody('hello');

        $this->assertNull($stream->getMetadata('key'));
    }
}
