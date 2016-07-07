<?php

namespace React\Tests\Http;

use Clue\React\Block;
use React\EventLoop\Factory;
use React\Http\DeferredStream;
use React\Http\File;
use React\Http\Parser\NoBody;
use React\Http\Request;
use React\Stream\ThroughStream;
use React\Tests\Http\Parser\DummyParser;

class DeferredStreamTest extends TestCase
{
    public function testDoneParser()
    {
        $parser = new NoBody(new Request('get', 'http://example.com'));
        $deferredStream = DeferredStream::create($parser);
        $result = Block\await($deferredStream, Factory::create(), 10);
        $this->assertSame([
            'post' => [],
            'files' => [],
            'body' => '',
        ], $result);
    }

    public function testDeferredStream()
    {
        $parser = new DummyParser(new Request('get', 'http://example.com'));
        $deferredStream = DeferredStream::create($parser);
        $parser->emit('post', ['foo', 'bar']);
        $parser->emit('post', ['array[]', 'foo']);
        $parser->emit('post', ['array[]', 'bar']);
        $parser->emit('post', ['dem[two]', 'bar']);
        $parser->emit('post', ['dom[two][]', 'bar']);

        $stream = new ThroughStream();
        $file = new File('foo', 'bar.ext', 'text', $stream);
        $parser->emit('file', [$file]);
        $stream->end('foo.bar');

        $parser->emit('body', ['abc']);

        $parser->emit('end');

        $result = Block\await($deferredStream, Factory::create(), 10);
        $this->assertSame([
            'foo' => 'bar',
            'array' => [
                'foo',
                'bar',
            ],
            'dem' => [
                'two' => 'bar',
            ],
            'dom' => [
                'two' => [
                    'bar',
                ],
            ],
        ], $result['post']);

        $this->assertSame('foo', $result['files'][0]['file']->getName());
        $this->assertSame('bar.ext', $result['files'][0]['file']->getFilename());
        $this->assertSame('text', $result['files'][0]['file']->getContentType());
        $this->assertSame('foo.bar', $result['files'][0]['buffer']);

        $this->assertSame('abc', $result['body']);
    }

    public function testExtractPost()
    {
        $postFields = [];
        DeferredStream::extractPost($postFields, 'dem', 'value');
        DeferredStream::extractPost($postFields, 'dom[one][two][]', 'value_a');
        DeferredStream::extractPost($postFields, 'dom[one][two][]', 'value_b');
        DeferredStream::extractPost($postFields, 'dam[]', 'value_a');
        DeferredStream::extractPost($postFields, 'dam[]', 'value_b');
        DeferredStream::extractPost($postFields, 'dum[sum]', 'value');
        $this->assertSame([
            'dem' => 'value',
            'dom' => [
                'one' => [
                    'two' => [
                        'value_a',
                        'value_b',
                    ],
                ],
            ],
            'dam' => [
                'value_a',
                'value_b',
            ],
            'dum' => [
                'sum' => 'value',
            ],
        ], $postFields);
    }
}
