<?php

namespace React\Tests\Http\Message;

use PHPUnit\Framework\TestCase;
use React\Http\Message\ResponseException;
use React\Http\Psr7\Response;

class ResponseExceptionTest extends TestCase
{
    public function testCtorDefaults()
    {
        $response = new Response();
        $response = $response->withStatus(404, 'File not found');

        $e = new ResponseException($response);

        $this->assertEquals(404, $e->getCode());
        $this->assertEquals('HTTP status code 404 (File not found)', $e->getMessage());

        $this->assertSame($response, $e->getResponse());
    }
}
