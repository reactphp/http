<?php

namespace React\Tests\Http\Io;

use React\Http\Io\UploadedFile;
Use React\Tests\Http\TestCase;
use RingCentral\Psr7\BufferStream;

class UploadedFileTest extends TestCase
{
    public function failtyErrorProvider()
    {
        return array(
            array('a'),
            array(null),
            array(-1),
            array(9),
        );
    }

    /**
     * @dataProvider failtyErrorProvider
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid error code, must be an UPLOAD_ERR_* constant
     */
    public function testFailtyError($error)
    {
        $stream = new BufferStream();
        new UploadedFile($stream, 0, $error, 'foo.bar', 'foo/bar');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Not implemented
     */
    public function testNoMoveFile()
    {
        $stream = new BufferStream();
        $uploadedFile = new UploadedFile($stream, 0, UPLOAD_ERR_OK, 'foo.bar', 'foo/bar');
        $uploadedFile->moveTo('bar.foo');
    }

    public function testGetters()
    {
        $stream = new BufferStream();
        $uploadedFile = new UploadedFile($stream, 0, UPLOAD_ERR_OK, 'foo.bar', 'foo/bar');
        self::assertSame($stream,       $uploadedFile->getStream());
        self::assertSame(0,             $uploadedFile->getSize());
        self::assertSame(UPLOAD_ERR_OK, $uploadedFile->getError());
        self::assertSame('foo.bar',     $uploadedFile->getClientFilename());
        self::assertSame('foo/bar',     $uploadedFile->getClientMediaType());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot retrieve stream due to upload error
     */
    public function testGetStreamOnFailedUpload()
    {
        $stream = new BufferStream();
        $uploadedFile = new UploadedFile($stream, 0, UPLOAD_ERR_NO_FILE, 'foo.bar', 'foo/bar');
        $uploadedFile->getStream();
    }
}
