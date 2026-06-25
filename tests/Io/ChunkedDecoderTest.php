<?php

namespace React\Tests\Http\Io;

use React\Http\Io\ChunkedDecoder;
use React\Stream\ReadableStreamInterface;
use React\Stream\ThroughStream;
use React\Stream\WritableStreamInterface;
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

        $this->input->emit('data', ["5\r\nhello\r\n"]);
    }

    public function testTwoChunks()
    {
        $buffer = [];
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });

        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());

        $this->input->emit('data', ["5\r\nhello\r\n3\r\nbla\r\n"]);

        $this->assertEquals(['hello', 'bla'], $buffer);
    }

    public function testEnd()
    {
        $this->parser->on('end', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', ["0\r\n\r\n"]);
    }

    public function testParameterWithEnd()
    {
        $buffer = [];
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });

        $this->parser->on('end', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', ["5\r\nhello\r\n3\r\nbla\r\n0\r\n\r\n"]);

        $this->assertEquals(['hello', 'bla'], $buffer);
    }

    public function testInvalidChunk()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('error', $this->expectCallableOnce());

        $this->input->emit('data', ["bla\r\n"]);
    }

    public function testNeverEnd()
    {
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', ["0\r\n"]);
    }

    public function testWrongChunkHex()
    {
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());

        $this->input->emit('data', ["2\r\na\r\n5\r\nhello\r\n"]);
    }

    public function testSplittedChunk()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('welt'));
        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', ["4\r\n"]);
        $this->input->emit('data', ["welt\r\n"]);
    }

    public function testSplittedHeader()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('welt'));
        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());#
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', ["4"]);
        $this->input->emit('data', ["\r\nwelt\r\n"]);
    }

    public function testSplittedBoth()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('welt'));
        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', ["4"]);
        $this->input->emit('data', ["\r\n"]);
        $this->input->emit('data', ["welt\r\n"]);
    }

    public function testCompletlySplitted()
    {
        $buffer = [];
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });

        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', ["4"]);
        $this->input->emit('data', ["\r\n"]);
        $this->input->emit('data', ["we"]);
        $this->input->emit('data', ["lt\r\n"]);

        $this->assertEquals(['we', 'lt'], $buffer);
    }

    public function testMixed()
    {
        $buffer = [];
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });

        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', ["4"]);
        $this->input->emit('data', ["\r\n"]);
        $this->input->emit('data', ["welt\r\n"]);
        $this->input->emit('data', ["5\r\nhello\r\n"]);

        $this->assertEquals(['welt', 'hello'], $buffer);
    }

    public function testBigger()
    {
        $buffer = [];
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });

        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', ["1"]);
        $this->input->emit('data', ["0"]);
        $this->input->emit('data', ["\r\n"]);
        $this->input->emit('data', ["abcdeabcdeabcdea\r\n"]);
        $this->input->emit('data', ["5\r\nhello\r\n"]);

        $this->assertEquals(['abcdeabcdeabcdea', 'hello'], $buffer);
    }

    public function testOneUnfinished()
    {
        $buffer = [];
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });

        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', ["3\r\n"]);
        $this->input->emit('data', ["bla\r\n"]);
        $this->input->emit('data', ["5\r\nhello"]);

        $this->assertEquals(['bla', 'hello'], $buffer);
    }

    public function testChunkIsBiggerThenExpected()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('hello'));
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());

        $this->input->emit('data', ["5\r\n"]);
        $this->input->emit('data', ["hello world\r\n"]);
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

        $this->input->emit('data', ["3;hello=world;foo=bar\r\nbla"]);
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
        $this->input->emit('data', [$data]);
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

        $this->input->emit('data', [$data]);
    }

    public function testLateCrlf()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('late'));
        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', ["4\r\nlate"]);
        $this->input->emit('data', ["\r"]);
        $this->input->emit('data', ["\n"]);
    }

    public function testNoCrlfInChunk()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('no'));
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());

        $this->input->emit('data', ["2\r\nno crlf"]);
    }

    public function testNoCrlfInChunkSplitted()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('no'));
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());

        $this->input->emit('data', ["2\r\n"]);
        $this->input->emit('data', ["no"]);
        $this->input->emit('data', ["further"]);
        $this->input->emit('data', ["clrf"]);
    }

    public function testEmitEmptyChunkBody()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', ["2\r\n"]);
        $this->input->emit('data', [""]);
        $this->input->emit('data', [""]);
    }

    public function testEmitCrlfAsChunkBody()
    {
        $this->parser->on('data', $this->expectCallableOnceWith("\r\n"));
        $this->parser->on('close', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());

        $this->input->emit('data', ["2\r\n"]);
        $this->input->emit('data', ["\r\n"]);
        $this->input->emit('data', ["\r\n"]);
    }

    public function testNegativeHeader()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());

        $this->input->emit('data', ["-2\r\n"]);
    }

    public function testHexDecimalInBodyIsPotentialThread()
    {
        $this->parser->on('data', $this->expectCallableOnce('test'));
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());

        $this->input->emit('data', ["4\r\ntest5\r\nworld"]);
    }

    public function testHexDecimalInBodyIsPotentialThreadSplitted()
    {
        $this->parser->on('data', $this->expectCallableOnce('test'));
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());

        $this->input->emit('data', ["4"]);
        $this->input->emit('data', ["\r\n"]);
        $this->input->emit('data', ["test"]);
        $this->input->emit('data', ["5"]);
        $this->input->emit('data', ["\r\n"]);
        $this->input->emit('data', ["world"]);
    }

    public function testEmitSingleCharacter()
    {
        $buffer = [];
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableOnce());
        $this->parser->on('error', $this->expectCallableNever());

        $array = str_split("4\r\ntest\r\n0\r\n\r\n");

        foreach ($array as $character) {
            $this->input->emit('data', [$character]);
        }

        $this->assertEquals(['t', 'e', 's', 't'], $buffer);
    }

    public function testHandleError()
    {
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());

        $this->input->emit('error', [new \RuntimeException()]);

        $this->assertFalse($this->parser->isReadable());
    }

    public function testPauseStream()
    {
        $input = $this->createMock(ReadableStreamInterface::class);
        $input->expects($this->once())->method('pause');

        $parser = new ChunkedDecoder($input);
        $parser->pause();
    }

    public function testResumeStream()
    {
        $input = $this->createMock(ReadableStreamInterface::class);
        $input->expects($this->once())->method('pause');

        $parser = new ChunkedDecoder($input);
        $parser->pause();
        $parser->resume();
    }

    public function testPipeStream()
    {
        $dest = $this->createMock(WritableStreamInterface::class);

        $ret = $this->parser->pipe($dest);

        $this->assertSame($dest, $ret);
    }

    public function testHandleClose()
    {
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->close();
        $this->input->emit('end', []);

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
        $buffer = [];
        $this->parser->on('data', function ($data) use (&$buffer) {
            $buffer[] = $data;
        });

        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());

        $this->input->emit('data', ["00005\r\nhello\r\n"]);
        $this->input->emit('data', ["0000b\r\nhello world\r\n"]);

        $this->assertEquals(['hello', 'hello world'], $buffer);
    }

    public function testLeadingZerosInEndChunkWillBeIgnored()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('data', ["0000\r\n\r\n"]);
    }

    public function testAdditionalWhitespaceInEndChunkWillBeIgnored()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('data', [" 0 \r\n\r\n"]);
    }

    public function testEndChunkWithTrailersWillBeIgnored()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('data', ["0\r\nFoo: bar\r\n\r\n"]);
    }

    public function testEndChunkWithMultipleTrailersWillBeIgnored()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('data', ["0\r\nFoo: a\r\nBar: b\r\nBaz: c\r\n\r\n"]);
    }

    public function testEndChunkWithIncompleteTrailerWithoutCrlfWillWaitForAdditionalDataAndNotCauseInfiniteLoop()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());

        // malformed end chunk with trailing data but without a terminating CRLF
        // must not loop forever, but wait for additional data
        $this->input->emit('data', ["0\r\nab"]);
    }

    public function testEndChunkWithIncompleteTrailerWillEndOnceTrailerIsCompleted()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('data', ["0\r\nab"]);
        $this->input->emit('data', ["\r\n\r\n"]);
    }

    public function testChunkFollowedByExactlyTwoNonCrlfBytesWillErrorAndNotCauseInfiniteLoop()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('ab'));
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());

        // completed chunk followed by exactly two bytes that are not a CRLF must not
        // loop forever, but report an invalid chunk terminator
        $this->input->emit('data', ["2\r\nabXY"]);
    }

    public function testLeadingZerosInInvalidChunk()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('data', ["0000hello\r\n\r\n"]);
    }

    public function testEmptyHeaderLeadsToError()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('data', ["\r\n\r\n"]);
    }

    public function testEmptyHeaderAndFilledBodyLeadsToError()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->input->emit('data', ["\r\nhello\r\n"]);
    }

    public function testUpperCaseHexWillBeHandled()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('0123456790'));
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());

        $this->input->emit('data', ["A\r\n0123456790\r\n"]);
    }

    public function testLowerCaseHexWillBeHandled()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('0123456790'));
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());

        $this->input->emit('data', ["a\r\n0123456790\r\n"]);
    }

    public function testMixedUpperAndLowerCaseHexValuesInHeaderWillBeHandled()
    {
        $data = str_repeat('1', (int)hexdec('AA'));

        $this->parser->on('data', $this->expectCallableOnceWith($data));
        $this->parser->on('error', $this->expectCallableNever());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableNever());

        $this->input->emit('data', ["aA\r\n" . $data . "\r\n"]);
    }
}
