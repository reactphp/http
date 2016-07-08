<?php

namespace React\Tests\Http\StreamingBodyParser;

use React\Http\StreamingBodyParser\RawBody;
use React\Http\Request;
use React\Tests\Http\TestCase;

class RawBodyTest extends TestCase
{
    public function testNoContentLength()
    {
        $body = '';
        $request = new Request('POST', 'http://example.com/', [], 1.1, [
            'content-length' => 3,
        ]);
        $parser = new RawBody($request);
        $parser->on('body', function ($rawBody) use (&$body) {
            $body = $rawBody;
        });
        $request->emit('data', ['abc']);
        $this->assertSame('abc', $body);
    }
}
