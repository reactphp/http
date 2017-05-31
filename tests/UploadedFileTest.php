<?php

namespace React\Tests\Http;

use React\Http\Response;
use React\Http\UploadedFile;
use React\Stream\ThroughStream;
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
     */
    public function testFailtyError($error)
    {
        self::expectException('\InvalidArgumentException');
        self::expectExceptionMessage('Invalid error code, must be an UPLOAD_ERR_* constant');
        $stream = new BufferStream();
        new UploadedFile($stream, 0, $error, 'foo.bar', 'foo/bar');
    }

    public function testNoMoveFile()
    {
        self::expectException('\RuntimeException');
        self::expectExceptionMessage('Not implemented');
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

    public function testGetStreamOnFailedUpload()
    {
        self::expectException('\RuntimeException');
        self::expectExceptionMessage('Cannot retrieve stream due to upload error');
        $stream = new BufferStream();
        $uploadedFile = new UploadedFile($stream, 0, UPLOAD_ERR_NO_FILE, 'foo.bar', 'foo/bar');
        $uploadedFile->getStream();
    }
}
