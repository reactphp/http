<?php

namespace React\Tests\Http;

use React\Http\FormUrlencodedParser;
use React\Http\Request;
use React\Stream\ThroughStream;

class FormUrlencodedParserTest extends TestCase
{
    public function testParse()
    {
        $stream = new ThroughStream();
        $request = new Request('POST', 'http://example.com/');
        new FormUrlencodedParser($stream, $request);
        $stream->write('user=single&user2=second&us');
        $stream->end('ers%5B%5D=first+in+array&users%5B%5D=second+in+array');
        $this->assertEquals(
            ['user' => 'single', 'user2' => 'second', 'users' => ['first in array', 'second in array']],
            $request->getPost()
        );
    }
}
