<?php

namespace React\Tests\Http\Message;

use React\Http\Message\ResponseException;
use PHPUnit\Framework\TestCase;
use RingCentral\Psr7\Request;
use RingCentral\Psr7\Response;

class ResponseExceptionTest extends TestCase
{
    public function testCtorDefaults()
    {
        $response = new Response();
        $request = new Request('get', 'https://example.com/');
        $response = $response->withStatus(404, 'File not found');

        $e = new ResponseException($response, $request);

        $this->assertEquals(404, $e->getCode());
        $this->assertEquals('HTTP status code 404 (File not found)', $e->getMessage());

        $this->assertSame($response, $e->getResponse());
        $this->assertSame($request, $e->getRequest());
    }
}
