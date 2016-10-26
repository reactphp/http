<?php

namespace React\Tests\Http\StreamingBodyParser;

use Clue\React\Block;
use React\EventLoop\Factory;
use React\Http\File;
use React\Http\StreamingBodyParser\BufferedSink;
use React\Http\StreamingBodyParser\NoBodyParser;
use React\Http\Request;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;

class BodyBufferedSinkTest extends TestCase
{
    public function testDoneParser()
    {
        $parser = new NoBodyParser(new Request('get', 'http://example.com'));
        $deferredStream = BufferedSink::createPromise($parser);
        $result = Block\await($deferredStream, Factory::create(), 10);
        $this->assertSame('', $result);
    }

    public function testDeferredStream()
    {
        $parser = new DummyParser(new Request('get', 'http://example.com'));
        $deferredStream = BufferedSink::createPromise($parser);

        $parser->emit('body', ['abc']);
        $parser->emit('end');

        $result = Block\await($deferredStream, Factory::create(), 10);

        $this->assertSame('abc', $result);
    }
}
