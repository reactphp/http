<?php

namespace React\Tests\Http\StreamingBodyParser;

use React\Http\Request;
use React\Http\StreamingBodyParser\NoBodyParser;
use React\Tests\Http\TestCase;

class NoBodyParserTest extends TestCase
{
    public function testRequestEnd()
    {
        $request = new Request('POST', 'http://example.com/');
        $parser = NoBodyParser::create($request);
        $parser->on('end', $this->expectCallableOnce());
        $request->close();
    }

    public function testCancelParser()
    {
        $request = new Request('POST', 'http://example.com/');
        $parser = NoBodyParser::create($request);
        $parser->on('end', $this->expectCallableOnce());
        $parser->cancel();
    }
}
