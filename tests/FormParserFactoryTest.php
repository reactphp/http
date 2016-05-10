<?php

namespace React\Tests\Http;

use React\Http\FormParserFactory;
use React\Http\Request;

class FormParserFactoryTest extends TestCase
{
    public function testMultipart()
    {
        $request = new Request('POST', 'http://example.com/', [], 1.1, [
            'Content-Type' => 'multipart/mixed; boundary=---------------------------12758086162038677464950549563',
        ]);
        $parser = FormParserFactory::create($request);
        $this->assertInstanceOf('React\Http\Parser\Multipart', $parser);
    }

    public function testMultipartUTF8()
    {
        $request = new Request('POST', 'http://example.com/', [], 1.1, [
            'Content-Type' => 'multipart/mixed; boundary=---------------------------12758086162038677464950549563; charset=utf8',
        ]);
        $parser = FormParserFactory::create($request);
        $this->assertInstanceOf('React\Http\Parser\Multipart', $parser);
    }

    public function testMultipartHeaderCaseInsensitive()
    {
        $request = new Request('POST', 'http://example.com/', [], 1.1, [
            'CONTENT-TYPE' => 'multipart/mixed; boundary=---------------------------12758086162038677464950549563',
        ]);
        $parser = FormParserFactory::create($request);
        $this->assertInstanceOf('React\Http\Parser\Multipart', $parser);
    }

    public function testFormUrlencoded()
    {
        $request = new Request('POST', 'http://example.com/', [], 1.1, [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'content-length' => 123,
        ]);
        $parser = FormParserFactory::create($request);
        $this->assertInstanceOf('React\Http\Parser\FormUrlencoded', $parser);
    }

    public function testFormUrlencodedUTF8()
    {
        $request = new Request('POST', 'http://example.com/', [], 1.1, [
            'Content-Type' => 'application/x-www-form-urlencoded; charset=utf8',
            'content-length' => 123,
        ]);
        $parser = FormParserFactory::create($request);
        $this->assertInstanceOf('React\Http\Parser\FormUrlencoded', $parser);
    }

    public function testFormUrlencodedHeaderCaseInsensitive()
    {
        $request = new Request('POST', 'http://example.com/', [], 1.1, [
            'content-type' => 'application/x-www-form-urlencoded',
            'content-length' => 123,
        ]);
        $parser = FormParserFactory::create($request);
        $this->assertInstanceOf('React\Http\Parser\FormUrlencoded', $parser);
    }
}
