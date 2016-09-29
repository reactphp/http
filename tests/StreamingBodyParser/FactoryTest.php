<?php

namespace React\Tests\Http\StreamingBodyParser;

use React\Http\StreamingBodyParser\Factory;
use React\Http\Request;
use React\Tests\Http\TestCase;

class FactoryTest extends TestCase
{
    public function testContentLength()
    {
        $request = new Request('POST', 'http://example.com/', [], 1.1, [
            'content-length' => 123,
        ]);
        $parser = Factory::create($request);
        $this->assertInstanceOf('React\Http\StreamingBodyParser\RawBodyParser', $parser);
    }

    public function testNoHeaders()
    {
        $request = new Request('POST', 'http://example.com/');
        $parser = Factory::create($request);
        $this->assertInstanceOf('React\Http\StreamingBodyParser\NoBodyParser', $parser);
    }
}
