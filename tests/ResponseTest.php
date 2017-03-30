<?php

namespace React\Tests\Http;

use React\Http\Response;
use React\Stream\ReadableStream;

class ResponseTest extends TestCase
{
    public function testResponseBodyWillBeHttpBodyStream()
    {
        $response = new Response(200, array(), new ReadableStream());
        $this->assertInstanceOf('React\Http\HttpBodyStream', $response->getBody());
    }

    public function testStringBodyWillBePsr7Stream()
    {
        $response = new Response(200, array(), 'hello');
        $this->assertInstanceOf('RingCentral\Psr7\Stream', $response->getBody());
    }
}
