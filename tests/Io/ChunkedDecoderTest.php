<?php

namespace React\Tests\Http\Io;

use React\Http\Io\ChunkedDecoder;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;

class ChunkedDecoderTest extends TestCase
{
    private $input;
    private $parser;

    /**
     * @before
     */
    public function setUpParser()
    {
        $this->input = new ThroughStream();
        $this->parser = new ChunkedDecoder($this->input);
    }

    public function testSimpleChunk()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('hello'));
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());

        $this->input->emit('data', array("5\r\nhello\r\n"));
    }

    public function testTwoChunks()
    {
        $buffer = array();
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });

        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());

        $this->input->emit('data', array("5\r\nhello\r\n3\r\nbla\r\n"));

        $this->assertEquals(array('hello', 'bla'), $buffer);
    }

    public function testEnd()
    {
        $this->parser->on('end', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', array("0\r\n\r\n"));
    }

    public function testParameterWithEnd()
    {
        $buffer = array();
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });

        $this->parser->on('end', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', array("5\r\nhello\r\n3\r\nbla\r\n0\r\n\r\n"));

        $this->assertEquals(array('hello', 'bla'), $buffer);
    }

    public function testInvalidChunk()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('error', $this->expectCallableOnce());

        $this->input->emit('data', array("bla\r\n"));
    }

    public function testNeverEnd()
    {
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', array("0\r\n"));
    }

    public function testWrongChunkHex()
    {
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());

        $this->input->emit('data', array("2\r\na\r\n5\r\nhello\r\n"));
    }

    public function testSplittedChunk()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('welt'));
        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', array("4\r\n"));
        $this->input->emit('data', array("welt\r\n"));
    }

    public function testSplittedHeader()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('welt'));
        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());#
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', array("4"));
        $this->input->emit('data', array("\r\nwelt\r\n"));
    }

    public function testSplittedBoth()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('welt'));
        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', array("4"));
        $this->input->emit('data', array("\r\n"));
        $this->input->emit('data', array("welt\r\n"));
    }

    public function testCompletlySplitted()
    {
        $buffer = array();
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });

        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', array("4"));
        $this->input->emit('data', array("\r\n"));
        $this->input->emit('data', array("we"));
        $this->input->emit('data', array("lt\r\n"));

        $this->assertEquals(array('we', 'lt'), $buffer);
    }

    public function testMixed()
    {
        $buffer = array();
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });

        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', array("4"));
        $this->input->emit('data', array("\r\n"));
        $this->input->emit('data', array("welt\r\n"));
        $this->input->emit('data', array("5\r\nhello\r\n"));

        $this->assertEquals(array('welt', 'hello'), $buffer);
    }

    public function testBigger()
    {
        $buffer = array();
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });

        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', array("1"));
        $this->input->emit('data', array("0"));
        $this->input->emit('data', array("\r\n"));
        $this->input->emit('data', array("abcdeabcdeabcdea\r\n"));
        $this->input->emit('data', array("5\r\nhello\r\n"));

        $this->assertEquals(array('abcdeabcdeabcdea', 'hello'), $buffer);
    }

    public function testOneUnfinished()
    {
        $buffer = array();
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });

        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', array("3\r\n"));
        $this->input->emit('data', array("bla\r\n"));
        $this->input->emit('data', array("5\r\nhello"));

        $this->assertEquals(array('bla', 'hello'), $buffer);
    }

    public function testChunkIsBiggerThenExpected()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('hello'));
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());

        $this->input->emit('data', array("5\r\n"));
        $this->input->emit('data', array("hello world\r\n"));
    }

    public function testHandleUnexpectedEnd()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());

        $this->input->emit('end');
    }

    public function testExtensionWillBeIgnored()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('bla'));
        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', array("3;hello=world;foo=bar\r\nbla"));
    }

    public function testChunkHeaderIsTooBig()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());

        $data = '';
        for ($i = 0; $i < 1025; $i++) {
            $data .= 'a';
        }
        $this->input->emit('data', array($data));
    }

    public function testChunkIsMaximumSize()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());

        $data = '';
        for ($i = 0; $i < 1024; $i++) {
            $data .= 'a';
        }
        $data .= "\r\n";

        $this->input->emit('data', array($data));
    }

    public function testLateCrlf()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('late'));
        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', array("4\r\nlate"));
        $this->input->emit('data', array("\r"));
        $this->input->emit('data', array("\n"));
    }

    public function testNoCrlfInChunk()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('no'));
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());

        $this->input->emit('data', array("2\r\nno crlf"));
    }

    public function testNoCrlfInChunkSplitted()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('no'));
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());

        $this->input->emit('data', array("2\r\n"));
        $this->input->emit('data', array("no"));
        $this->input->emit('data', array("further"));
        $this->input->emit('data', array("clrf"));
    }

    public function testEmitEmptyChunkBody()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', array("2\r\n"));
        $this->input->emit('data', array(""));
        $this->input->emit('data', array(""));
    }

    public function testEmitCrlfAsChunkBody()
    {
        $this->parser->on('data', $this->expectCallableOnceWith("\r\n"));
        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', array("2\r\n"));
        $this->input->emit('data', array("\r\n"));
        $this->input->emit('data', array("\r\n"));
    }

    public function testNegativeHeader()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());

        $this->input->emit('data', array("-2\r\n"));
    }

    public function testHexDecimalInBodyIsPotentialThread()
    {
        $this->parser->on('data', $this->expectCallableOnce('test'));
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());

        $this->input->emit('data', array("4\r\ntest5\r\nworld"));
    }

    public function testHexDecimalInBodyIsPotentialThreadSplitted()
    {
        $this->parser->on('data', $this->expectCallableOnce('test'));
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());

        $this->input->emit('data', array("4"));
        $this->input->emit('data', array("\r\n"));
        $this->input->emit('data', array("test"));
        $this->input->emit('data', array("5"));
        $this->input->emit('data', array("\r\n"));
        $this->input->emit('data', array("world"));
    }

    public function testEmitSingleCharacter()
    {
        $buffer = array();
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableOnce());
        $this->parser->on('error', $this->expectCallableNever());

        $array = str_split("4\r\ntest\r\n0\r\n\r\n");

        foreach ($array as $character) {
            $this->input->emit('data', array($character));
        }

        $this->assertEquals(array('t', 'e', 's', 't'), $buffer);
    }

    public function testHandleError()
    {
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());

        $this->input->emit('error', array(new \RuntimeException()));

        $this->assertFalse($this->parser->isReadable());
    }

    public function testPauseStream()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('pause');

        $parser = new ChunkedDecoder($input);
        $parser->pause();
    }

    public function testResumeStream()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('pause');

        $parser = new ChunkedDecoder($input);
        $parser->pause();
        $parser->resume();
    }

    public function testPipeStream()
    {
        $dest = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

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
        $input = new ThroughStream();
        $input->on('close', $this->expectCallableOnce());

        $stream = new ChunkedDecoder($input);
        $stream->on('close', $this->expectCallableOnce());

        $stream->close();

        $this->assertFalse($input->isReadable());
    }

    public function testLeadingZerosWillBeIgnored()
    {
        $buffer = array();
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });

        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());

        $this->input->emit('data', array("00005\r\nhello\r\n"));
        $this->input->emit('data', array("0000b\r\nhello world\r\n"));

        $this->assertEquals(array('hello', 'hello world'), $buffer);
    }

    public function testLeadingZerosInEndChunkWillBeIgnored()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('data', array("0000\r\n\r\n"));
    }

    public function testAdditionalWhitespaceInEndChunkWillBeIgnored()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('data', array(" 0 \r\n\r\n"));
    }

    public function testEndChunkWithTrailersWillBeIgnored()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('data', array("0\r\nFoo: bar\r\n\r\n"));
    }

    public function testEndChunkWithMultipleTrailersWillBeIgnored()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('data', array("0\r\nFoo: a\r\nBar: b\r\nBaz: c\r\n\r\n"));
    }

    public function testLeadingZerosInInvalidChunk()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('data', array("0000hello\r\n\r\n"));
    }

    public function testEmptyHeaderLeadsToError()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('data', array("\r\n\r\n"));
    }

    public function testEmptyHeaderAndFilledBodyLeadsToError()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('data', array("\r\nhello\r\n"));
    }

    public function testUpperCaseHexWillBeHandled()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('0123456790'));
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());

        $this->input->emit('data', array("A\r\n0123456790\r\n"));
    }

    public function testLowerCaseHexWillBeHandled()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('0123456790'));
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());

        $this->input->emit('data', array("a\r\n0123456790\r\n"));
    }

    public function testMixedUpperAndLowerCaseHexValuesInHeaderWillBeHandled()
    {
        $data = str_repeat('1', (int)hexdec('AA'));

        $this->parser->on('data', $this->expectCallableOnceWith($data));
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());

        $this->input->emit('data', array("aA\r\n" . $data . "\r\n"));
    }
}
