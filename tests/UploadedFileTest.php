<?php

namespace React\Tests\Http;

use React\Http\UploadedFile;
use React\Stream\ThroughStream;

class UploadedFileTest extends TestCase
{
    public function testGetters()
    {
        $filename = 'bar.txt';
        $type = 'text/text';
        $stream = new ThroughStream();
        $file = new UploadedFile($filename, $type, $stream);
        $this->assertEquals($filename, $file->getClientFilename());
        $this->assertEquals($type, $file->getClientMediaType());
        $this->assertEquals($stream, $file->getStream());
    }
}
