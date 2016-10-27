<?php

namespace React\Tests\Http\StreamingBodyParser;

use React\Http\StreamingBodyParser\RawBodyParser;
use React\Http\Request;
use React\Tests\Http\TestCase;

class RawBodyParserTest extends TestCase
{
    public function testNoBufferingLength()
    {
        $body = '';
        $request = new Request('POST', 'http://example.com/', [], 1.1, [
            'content-length' => 3,
        ]);
        $parser = RawBodyParser::create($request);
        $parser->on('end', $this->expectCallableNever());
        $parser->on('body', function ($rawBody) use (&$body) {
            $body = $rawBody;
        });
        $request->emit('data', ['abc']);
        $this->assertSame('abc', $body);
        $request->emit('data', ['def']);
        $this->assertSame('def', $body);
    }

    public function testEndForward()
    {
        $request = new Request('POST', 'http://example.com/');
        $parser = RawBodyParser::create($request);
        $parser->on('end', $this->expectCallableOnce());
        $parser->on('body', $this->expectCallableNever());
        $request->emit('end');
    }

    public function testEndOnCancel()
    {
        $request = new Request('POST', 'http://example.com/');
        $parser = RawBodyParser::create($request);
        $parser->on('end', $this->expectCallableOnce());
        $parser->on('body', $this->expectCallableNever());
        $parser->cancel();
    }
}
