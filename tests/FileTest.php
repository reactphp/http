<?php

namespace React\Tests\Http;

use React\Http\File;
use React\Stream\ThroughStream;

class FileTest extends TestCase
{
    public function testGetters()
    {
        $filename = 'bar.txt';
        $type = 'text/text';
        $stream = new ThroughStream();
        $file = new File($filename, $type, $stream);
        $this->assertEquals($filename, $file->getFilename());
        $this->assertEquals($type, $file->getContentType());
        $this->assertEquals($stream, $file->getStream());
    }
}
