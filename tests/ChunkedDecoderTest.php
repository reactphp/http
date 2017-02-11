<?php

namespace React\Tests\Http;

use React\Stream\ReadableStream;
use React\Http\ChunkedDecoder;

class ChunkedDecoderTest extends TestCase
{
    public function setUp()
    {
        $this->input = new ReadableStream();
        $this->parser = new ChunkedDecoder($this->input);
    }

    public function testSimpleChunk()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('hello'));
        $this->input->emit('data', array("5\r\nhello\r\n"));
    }

    public function testTwoChunks()
    {
    	$this->parser->on('data', $this->expectCallableConsecutive(2, array('hello', 'bla')));
    	$this->input->emit('data', array("5\r\nhello\r\n3\r\nbla\r\n"));
    }

    public function testEnd()
    {
    	$this->parser->on('end', $this->expectCallableOnce(array()));
    	$this->input->emit('data', array("0\r\n\r\n"));
    }

    public function testParameterWithEnd()
    {
    	$this->parser->on('data', $this->expectCallableConsecutive(2, array('hello', 'bla')));
    	$this->parser->on('end', $this->expectCallableOnce(array()));
    	$this->input->emit('data', array("5\r\nhello\r\n3\r\nbla\r\n0\r\n\r\n"));
    }

    public function testInvalidChunk()
    {
    	$this->parser->on('data', $this->expectCallableNever());
    	$this->parser->on('error', $this->expectCallableOnce(array()));
    	$this->input->emit('data', array("bla\r\n"));
    }

    public function testNeverEnd()
    {
    	$this->parser->on('end', $this->expectCallableNever());
    	$this->input->emit('data', array("0\r\n"));
    }

    public function testWrongChunkHex()
    {
    	$this->parser->on('error', $this->expectCallableOnce(array()));
    	$this->input->emit('data', array("2\r\na\r\n5\r\nhello\r\n"));
    }

    public function testSplittedChunk()
    {
        $this->parser->on('data',  $this->expectCallableOnceWith('welt'));

        $this->input->emit('data', array("4\r\n"));
        $this->input->emit('data', array("welt\r\n"));
    }

    public function testSplittedHeader()
    {
    	$this->parser->on('data',  $this->expectCallableOnceWith('welt'));

    	$this->input->emit('data', array("4"));
    	$this->input->emit('data', array("\r\nwelt\r\n"));
    }

    public function testSplittedBoth()
    {
    	$this->parser->on('data',  $this->expectCallableOnceWith('welt'));

    	$this->input->emit('data', array("4"));
    	$this->input->emit('data', array("\r\n"));
    	$this->input->emit('data', array("welt\r\n"));
    }

    public function testCompletlySplitted()
    {
    	$this->parser->on('data',  $this->expectCallableOnceWith('welt'));

    	$this->input->emit('data', array("4"));
    	$this->input->emit('data', array("\r\n"));
    	$this->input->emit('data', array("we"));
    	$this->input->emit('data', array("lt\r\n"));
    }

    public function testMixed()
    {
    	$this->parser->on('data',  $this->expectCallableConsecutive(2, array('welt', 'hello')));

    	$this->input->emit('data', array("4"));
    	$this->input->emit('data', array("\r\n"));
    	$this->input->emit('data', array("we"));
    	$this->input->emit('data', array("lt\r\n"));
    	$this->input->emit('data', array("5\r\nhello\r\n"));
    }

    public function testBigger()
    {
    	$this->parser->on('data',  $this->expectCallableConsecutive(2, array('abcdeabcdeabcdea', 'hello')));

    	$this->input->emit('data', array("1"));
    	$this->input->emit('data', array("0"));
    	$this->input->emit('data', array("\r\n"));
    	$this->input->emit('data', array("abcdeabcdeabcdea\r\n"));
    	$this->input->emit('data', array("5\r\nhello\r\n"));
    }

    public function testOneUnfinished()
    {
    	$this->parser->on('data', $this->expectCallableOnceWith('bla'));

    	$this->input->emit('data', array("3\r\n"));
    	$this->input->emit('data', array("bla\r\n"));
    	$this->input->emit('data', array("5\r\nhello"));
    }

    public function testHandleError()
    {
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('error', array(new \RuntimeException()));

        $this->assertFalse($this->parser->isReadable());
    }

    public function testPauseStream()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $input->expects($this->once())->method('pause');

        $parser = new ChunkedDecoder($input);
        $parser->pause();
    }

    public function testResumeStream()
    {
        $input = $this->getMock('React\Stream\ReadableStreamInterface');
        $input->expects($this->once())->method('pause');

        $parser = new ChunkedDecoder($input);
        $parser->pause();
        $parser->resume();
    }

    public function testPipeStream()
    {
        $dest = $this->getMock('React\Stream\WritableStreamInterface');

        $ret = $this->parser->pipe($dest);

        $this->assertSame($dest, $ret);
    }

    public function testHandleClose()
    {
    	$this->parser->on('close', $this->expectCallableOnce());

    	$this->input->close();
    	$this->input->emit('end', array());

    	$this->assertFalse($this->parser->isReadable());
    }

    public function testOutputStreamCanCloseInputStream()
    {
        $input = new ReadableStream();
        $input->on('close', $this->expectCallableOnce());

        $stream = new ChunkedDecoder($input);
        $stream->on('close', $this->expectCallableOnce());

        $stream->close();

        $this->assertFalse($input->isReadable());
    }
}
