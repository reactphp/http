<?php

namespace React\Tests\Http\StreamingBodyParser;

use React\Http\StreamingBodyParser\ContentLengthBufferedSink;
use React\Http\Request;
use React\Tests\Http\TestCase;

class ContentLengthBufferedSinkTest extends TestCase
{
    public function testCreatePromise()
    {
        $expectedBuffer = '0123456789';
        $catchedBuffer = '';
        $length = 10;
        $request = new Request('GET', 'http://example.com/');
        ContentLengthBufferedSink::createPromise($request, $length)->then(function ($buffer) use (&$catchedBuffer) {
            $catchedBuffer = $buffer;
        });
        $request->emit('data', ['012345678']);
        $request->emit('data', ['90123456789']);
        $this->assertSame($expectedBuffer, $catchedBuffer);
    }
}
