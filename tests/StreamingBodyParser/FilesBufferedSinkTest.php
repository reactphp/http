<?php

namespace React\Tests\Http\StreamingBodyParser;

use Clue\React\Block;
use React\EventLoop\Factory;
use React\Http\File;
use React\Http\StreamingBodyParser\FilesBufferedSink;
use React\Http\StreamingBodyParser\NoBodyParser;
use React\Http\Request;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;

class FilesBufferedSinkTest extends TestCase
{
    public function testDoneParser()
    {
        $parser = new NoBodyParser(new Request('get', 'http://example.com'));
        $deferredStream = FilesBufferedSink::createPromise($parser);
        $result = Block\await($deferredStream, Factory::create(), 10);
        $this->assertSame([], $result);
    }

    public function testDeferredStream()
    {
        $parser = new DummyParser(new Request('get', 'http://example.com'));
        $deferredStream = FilesBufferedSink::createPromise($parser);

        $stream = new ThroughStream();
        $file = new File('bar.ext', 'text', $stream);
        $parser->emit('file', ['foo', $file]);
        $stream->end('foo.bar');

        $parser->emit('end');

        $result = Block\await($deferredStream, Factory::create(), 10);

        $this->assertSame('foo', $result['files'][0]['name']);
        $this->assertSame('bar.ext', $result['files'][0]['file']->getFilename());
        $this->assertSame('text', $result['files'][0]['file']->getContentType());
        $this->assertSame('foo.bar', $result['files'][0]['buffer']);
    }
}
