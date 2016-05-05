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
        $post = [];
        $request = new Request('POST', 'http://example.com/', [], '1.1', [
            'Content-Length' => 79,
        ]);
        $parser = new FormUrlencoded($request);
        $parser->on('post', function ($key, $value) use (&$post) {
            $post[] = [$key, $value];
        });
        $request->emit('data', ['user=single&user2=second&us']);
        $request->emit('data', ['ers%5B%5D=first+in+array&users%5B%5D=second+in+array']);
        $this->assertEquals(
            [
                ['user', 'single'],
                ['user2', 'second'],
                ['users', ['first in array', 'second in array']],
            ],
            $post
        );
    }

    public function testNoContentLength()
    {
        $request = new Request('POST', 'http://example.com/');
        $parser = new FormUrlencoded($request);
        $this->assertTrue($parser->isDone());
    }
}
