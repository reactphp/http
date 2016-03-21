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
        $request = new Request('POST', 'http://example.com/');
        new FormUrlencoded($request);
        $request->emit('data', ['user=single&user2=second&us']);
        $request->emit('data', ['ers%5B%5D=first+in+array&users%5B%5D=second+in+array']);
        $request->emit('close');
        $this->assertEquals(
            ['user' => 'single', 'user2' => 'second', 'users' => ['first in array', 'second in array']],
            $request->getPost()
        );
    }
}
