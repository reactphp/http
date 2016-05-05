<?php

namespace React\Tests\Http\Parser;

use React\Http\Parser\NoBody;
use React\Http\Request;
use React\Tests\Http\TestCase;

class NoBodyTest extends TestCase
{
    public function testNoContentLength()
    {
        $request = new Request('POST', 'http://example.com/');
        $parser = new NoBody($request);
        $this->assertTrue($parser->isDone());
    }
}
