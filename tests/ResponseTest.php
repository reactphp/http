<?php

namespace React\Tests\Http;

use React\Http\Response;
use React\Stream\ThroughStream;

class ResponseTest extends TestCase
{
    public function testResponseBodyWillBeHttpBodyStream()
    {
        $response = new Response(200, array(), new ThroughStream());
        $this->assertInstanceOf('React\Http\Io\HttpBodyStream', $response->getBody());
    }

    public function testStringBodyWillBePsr7Stream()
    {
        $response = new Response(200, array(), 'hello');
        $this->assertInstanceOf('RingCentral\Psr7\Stream', $response->getBody());
    }
}
