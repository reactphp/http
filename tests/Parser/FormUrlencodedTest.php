<?php

namespace React\Tests\Http\Parser;

use React\Http\Parser\FormUrlencoded;
use React\Http\Request;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;

class FormUrlencodedTest extends TestCase
{
    public function testParse()
    {
        $stream = new ThroughStream();
        $request = new Request('POST', 'http://example.com/');
        new FormUrlencoded($stream, $request);
        $stream->write('user=single&user2=second&us');
        $stream->end('ers%5B%5D=first+in+array&users%5B%5D=second+in+array');
        $this->assertEquals(
            ['user' => 'single', 'user2' => 'second', 'users' => ['first in array', 'second in array']],
            $request->getPost()
        );
    }
}
