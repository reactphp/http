<?php

namespace React\Tests\Http;

use React\Http\File;
use React\Stream\ThroughStream;

class FileTest extends TestCase
{
    public function testGetters()
    {
        $name = 'foo';
        $filename = 'bar.txt';
        $type = 'text/text';
        $stream = new ThroughStream();
        $file = new File($name, $filename, $type, $stream);
        $this->assertEquals($name, $file->getName());
        $this->assertEquals($filename, $file->getFilename());
        $this->assertEquals($type, $file->getType());
        $this->assertEquals($stream, $file->getStream());
    }
}
