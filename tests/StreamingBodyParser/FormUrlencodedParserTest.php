<?php

namespace React\Tests\Http\StreamingBodyParser;

use React\Http\HttpBodyStream;
use React\Http\StreamingBodyParser\FormUrlencodedParser;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;
use RingCentral\Psr7\Request;

class FormUrlencodedParserTest extends TestCase
{
    public function testParse()
    {
        $post = array();
        $stream = new ThroughStream();
        $request = new Request('POST', 'http://example.com/', array(), new HttpBodyStream($stream, 0));
        $parser = new FormUrlencodedParser($request);
        $parser->on('post', function ($key, $value) use (&$post) {
            $post[] = array($key, $value);
        });
        $stream->emit('data', array('user=single&user2=second&us'));
        $this->assertEquals(
            array(
                array('user', 'single'),
                array('user2', 'second'),
            ),
            $post
        );
        $stream->emit('data', array('ers%5B%5D=first%20in%20array&users%5B%5D=second%20in%20array'));
        $this->assertEquals(
            array(
                array('user', 'single'),
                array('user2', 'second'),
                array('users[]', 'first in array'),
            ),
            $post
        );
        $stream->end();
        $this->assertEquals(
            array(
                array('user', 'single'),
                array('user2', 'second'),
                array('users[]', 'first in array'),
                array('users[]', 'second in array'),
            ),
            $post
        );
    }
}