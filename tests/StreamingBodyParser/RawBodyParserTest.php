<?php

namespace React\Tests\Http\StreamingBodyParser;

use React\Http\StreamingBodyParser\RawBodyParser;
use React\Http\Request;
use React\Tests\Http\TestCase;

class RawBodyParserTest extends TestCase
{
    public function testNoContentLength()
    {
        $body = '';
        $request = new Request('POST', 'http://example.com/', [], 1.1, [
            'content-length' => 3,
        ]);
        $parser = new RawBodyParser($request);
        $parser->on('body', function ($rawBody) use (&$body) {
            $body = $rawBody;
        });
        $request->emit('data', ['abc']);
        $this->assertSame('abc', $body);
    }
}
