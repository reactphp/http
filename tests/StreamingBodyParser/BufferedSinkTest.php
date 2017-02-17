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

class BufferedSinkTest extends TestCase
{
    public function testDoneParser()
    {
        $parser = new NoBodyParser(new Request('get', 'http://example.com'));
        $deferredStream = BufferedSink::createPromise($parser);
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
        $deferredStream = BufferedSink::createPromise($parser);
        $parser->emit('post', ['foo', 'bar']);
        $parser->emit('post', ['array[]', 'foo']);
        $parser->emit('post', ['array[]', 'bar']);
        $parser->emit('post', ['dem[two]', 'bar']);
        $parser->emit('post', ['dom[two][]', 'bar']);

        $stream = new ThroughStream();
        $file = new File('bar.ext', 'text', $stream);
        $parser->emit('file', ['foo', $file]);
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

        $this->assertSame('foo', $result['files'][0]['name']);
        $this->assertSame('bar.ext', $result['files'][0]['file']->getFilename());
        $this->assertSame('text', $result['files'][0]['file']->getContentType());
        $this->assertSame('foo.bar', $result['files'][0]['buffer']);

        $this->assertSame('abc', $result['body']);
    }

    public function testExtractPost()
    {
        $postFields = [];
        BufferedSink::extractPost($postFields, 'dem', 'value');
        BufferedSink::extractPost($postFields, 'dom[one][two][]', 'value_a');
        BufferedSink::extractPost($postFields, 'dom[one][two][]', 'value_b');
        BufferedSink::extractPost($postFields, 'dam[]', 'value_a');
        BufferedSink::extractPost($postFields, 'dam[]', 'value_b');
        BufferedSink::extractPost($postFields, 'dum[sum]', 'value');
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
