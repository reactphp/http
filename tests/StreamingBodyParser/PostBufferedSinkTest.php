<?php

namespace React\Tests\Http\StreamingBodyParser;

use Clue\React\Block;
use React\EventLoop\Factory;
use React\Http\StreamingBodyParser\PostBufferedSink;
use React\Http\StreamingBodyParser\NoBodyParser;
use React\Http\Request;
use React\Tests\Http\TestCase;

class PostPostBufferedSinkTest extends TestCase
{
    public function testDoneParser()
    {
        $parser = new NoBodyParser(new Request('get', 'http://example.com'));
        $deferredStream = PostBufferedSink::createPromise($parser);
        $result = Block\await($deferredStream, Factory::create(), 10);
        $this->assertSame([], $result);
    }

    public function testDeferredStream()
    {
        $parser = new DummyParser(new Request('get', 'http://example.com'));
        $deferredStream = PostBufferedSink::createPromise($parser);
        $parser->emit('post', ['foo', 'bar']);
        $parser->emit('post', ['array[]', 'foo']);
        $parser->emit('post', ['array[]', 'bar']);
        $parser->emit('post', ['dem[two]', 'bar']);
        $parser->emit('post', ['dom[two][]', 'bar']);

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
        ], $result);
    }

    public function testExtractPost()
    {
        $postFields = [];
        PostBufferedSink::extractPost($postFields, 'dem', 'value');
        PostBufferedSink::extractPost($postFields, 'dom[one][two][]', 'value_a');
        PostBufferedSink::extractPost($postFields, 'dom[one][two][]', 'value_b');
        PostBufferedSink::extractPost($postFields, 'dam[]', 'value_a');
        PostBufferedSink::extractPost($postFields, 'dam[]', 'value_b');
        PostBufferedSink::extractPost($postFields, 'dum[sum]', 'value');
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
