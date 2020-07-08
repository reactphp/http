<?php

namespace React\Tests\Http\Client;

use Exception;
use React\Http\Client\ChunkedStreamDecoder;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;

class DecodeChunkedStreamTest extends TestCase
{
    public function provideChunkedEncoding()
    {
        return array(
            'data-set-1' => array(
                array("4\r\nWiki\r\n5\r\npedia\r\ne\r\n in\r\n\r\nchunks.\r\n0\r\n\r\n"),
            ),
            'data-set-2' => array(
                array("4\r\nWiki\r\n", "5\r\npedia\r\ne\r\n in\r\n\r\nchunks.\r\n0\r\n\r\n"),
            ),
            'data-set-3' => array(
                array("4\r\nWiki\r\n", "5\r\n", "pedia\r\ne\r\n in\r\n\r\nchunks.\r\n0\r\n\r\n"),
            ),
            'data-set-4' => array(
                array("4\r\nWiki\r\n", "5\r\n", "pedia\r\ne\r\n in\r\n", "\r\nchunks.\r\n0\r\n\r\n"),
            ),
            'data-set-5' => array(
                array("4\r\n", "Wiki\r\n", "5\r\n", "pedia\r\ne\r\n in\r\n", "\r\nchunks.\r\n0\r\n\r\n"),
            ),
            'data-set-6' => array(
                array("4\r\n", "Wiki\r\n", "5\r\n", "pedia\r\ne; foo=[bar,beer,pool,cue,win,won]\r\n", " in\r\n", "\r\nchunks.\r\n0\r\n\r\n"),
            ),
            'header-fields' => array(
                array("4; foo=bar\r\n", "Wiki\r\n", "5\r\n", "pedia\r\ne\r\n", " in\r\n", "\r\nchunks.\r\n", "0\r\n\r\n"),
            ),
            'character-for-charactrr' => array(
                str_split("4\r\nWiki\r\n5\r\npedia\r\ne\r\n in\r\n\r\nchunks.\r\n0\r\n\r\n"),
            ),
            'extra-newline-in-wiki-character-for-chatacter' => array(
                str_split("6\r\nWi\r\nki\r\n5\r\npedia\r\ne\r\n in\r\n\r\nchunks.\r\n0\r\n\r\n"),
                "Wi\r\nkipedia in\r\n\r\nchunks."
            ),
            'extra-newline-in-wiki' => array(
                array("6\r\nWi\r\n", "ki\r\n5\r\npedia\r\ne\r\n in\r\n\r\nchunks.\r\n0\r\n\r\n"),
                "Wi\r\nkipedia in\r\n\r\nchunks."
            ),
            'varnish-type-response-1' => array(
                array("0017\r\nWikipedia in\r\n\r\nchunks.\r\n0\r\n\r\n")
            ),
            'varnish-type-response-2' => array(
                array("000017\r\nWikipedia in\r\n\r\nchunks.\r\n0\r\n\r\n")
            ),
            'varnish-type-response-3' => array(
                array("017\r\nWikipedia in\r\n\r\nchunks.\r\n0\r\n\r\n")
            ),
            'varnish-type-response-4' => array(
                array("004\r\nWiki\r\n005\r\npedia\r\n00e\r\n in\r\n\r\nchunks.\r\n0\r\n\r\n")
            ),
            'varnish-type-response-5' => array(
                array("000004\r\nWiki\r\n00005\r\npedia\r\n000e\r\n in\r\n\r\nchunks.\r\n0\r\n\r\n")
            ),
            'varnish-type-response-extra-line' => array(
                array("006\r\nWi\r\nki\r\n005\r\npedia\r\n00e\r\n in\r\n\r\nchunks.\r\n0\r\n\r\n"),
                "Wi\r\nkipedia in\r\n\r\nchunks."
            ),
            'varnish-type-response-random' => array(
                array(str_repeat("0", rand(0, 10)), "4\r\nWiki\r\n", str_repeat("0", rand(0, 10)), "5\r\npedia\r\n", str_repeat("0", rand(0, 10)), "e\r\n in\r\n\r\nchunks.\r\n0\r\n\r\n")
            ),
            'end-chunk-zero-check-1' => array(
                array("4\r\nWiki\r\n5\r\npedia\r\ne\r\n in\r\n\r\nchunks.\r\n00\r\n\r\n")
            ),
            'end-chunk-zero-check-2' => array(
                array("4\r\nWiki\r\n5\r\npedia\r\ne\r\n in\r\n\r\nchunks.\r\n000\r\n\r\n")
            ),
            'end-chunk-zero-check-3' => array(
                array("00004\r\nWiki\r\n005\r\npedia\r\ne\r\n in\r\n\r\nchunks.\r\n0000\r\n\r\n")
            ),
            'uppercase-chunk' => array(
                array("4\r\nWiki\r\n5\r\npedia\r\nE\r\n in\r\n\r\nchunks.\r\n0\r\n\r\n"),
            ),
            'extra-space-in-length-chunk' => array(
                array(" 04 \r\nWiki\r\n5\r\npedia\r\nE\r\n in\r\n\r\nchunks.\r\n0\r\n\r\n"),
            ),
            'only-whitespace-is-final-chunk' => array(
                array("   \r\n\r\n"),
                ""
            )
        );
    }

    /**
     * @test
     * @dataProvider provideChunkedEncoding
     */
    public function testChunkedEncoding(array $strings, $expected = "Wikipedia in\r\n\r\nchunks.")
    {
        $stream = new ThroughStream();
        $response = new ChunkedStreamDecoder($stream);
        $buffer = '';
        $response->on('data', function ($data) use (&$buffer) {
            $buffer .= $data;
        });
        $response->on('error', function ($error) {
            $this->fail((string)$error);
        });
        foreach ($strings as $string) {
            $stream->write($string);
        }
        $this->assertSame($expected, $buffer);
    }

    public function provideInvalidChunkedEncoding()
    {
        return array(
            'chunk-body-longer-than-header-suggests' => array(
                array("4\r\nWiwot40n98w3498tw3049nyn039409t34\r\n", "ki\r\n5\r\npedia\r\ne\r\n in\r\n\r\nchunks.\r\n0\r\n\r\n"),
            ),
            'invalid-header-charactrrs' => array(
                str_split("xyz\r\nWi\r\nki\r\n5\r\npedia\r\ne\r\n in\r\n\r\nchunks.\r\n0\r\n\r\n")
            ),
            'header-chunk-to-long' => array(
                str_split(str_repeat('a', 2015) . "\r\nWi\r\nki\r\n5\r\npedia\r\ne\r\n in\r\n\r\nchunks.\r\n0\r\n\r\n")
            )
        );
    }

    /**
     * @test
     * @dataProvider provideInvalidChunkedEncoding
     */
    public function testInvalidChunkedEncoding(array $strings)
    {
        $stream = new ThroughStream();
        $response = new ChunkedStreamDecoder($stream);
        $response->on('error', function (Exception $exception) {
            throw $exception;
        });

        $this->setExpectedException('Exception');
        foreach ($strings as $string) {
            $stream->write($string);
        }
    }

    public function provideZeroChunk()
    {
        return array(
            array('1-zero' => "0\r\n\r\n"),
            array('random-zero' => str_repeat("0", rand(2, 10))."\r\n\r\n")
        );
    }

    /**
     * @test
     * @dataProvider provideZeroChunk
     */
    public function testHandleEnd($zeroChunk)
    {
        $ended = false;
        $stream = new ThroughStream();
        $response = new ChunkedStreamDecoder($stream);
        $response->on('error', function ($error) {
            $this->fail((string)$error);
        });
        $response->on('end', function () use (&$ended) {
            $ended = true;
        });

        $stream->write("4\r\nWiki\r\n".$zeroChunk);

        $this->assertTrue($ended);
    }

    public function testHandleEndIncomplete()
    {
        $exception = null;
        $stream = new ThroughStream();
        $response = new ChunkedStreamDecoder($stream);
        $response->on('error', function ($e) use (&$exception) {
            $exception = $e;
        });

        $stream->end("4\r\nWiki");

        $this->assertInstanceOf('Exception', $exception);
    }

    public function testHandleEndTrailers()
    {
        $ended = false;
        $stream = new ThroughStream();
        $response = new ChunkedStreamDecoder($stream);
        $response->on('error', function ($error) {
            $this->fail((string)$error);
        });
        $response->on('end', function () use (&$ended) {
            $ended = true;
        });

        $stream->write("4\r\nWiki\r\n0\r\nabc: def\r\nghi: klm\r\n\r\n");

        $this->assertTrue($ended);
    }

    /**
     * @test
     * @dataProvider provideZeroChunk
     */
    public function testHandleEndEnsureNoError($zeroChunk)
    {
        $ended = false;
        $stream = new ThroughStream();
        $response = new ChunkedStreamDecoder($stream);
        $response->on('error', function ($error) {
            $this->fail((string)$error);
        });
        $response->on('end', function () use (&$ended) {
            $ended = true;
        });

        $stream->write("4\r\nWiki\r\n");
        $stream->write($zeroChunk);
        $stream->end();

        $this->assertTrue($ended);
    }
}
