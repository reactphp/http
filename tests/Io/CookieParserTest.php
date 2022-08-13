<?php

namespace React\Tests\Http\Io;

use PHPUnit\Framework\TestCase;
use React\Http\Io\Clock;
use React\Http\Io\CookieParser;
use React\Http\Message\ServerRequest;

class CookieParserTest extends TestCase
{
    public function testParseSingleCookieNameValuePairWillReturnValidArray()
    {
        $this->assertEquals(array('hello' => 'world'), CookieParser::parse('hello=world'));
    }

    public function testParseMultipleCookieNameValuePairWillReturnValidArray()
    {
        $this->assertEquals(array('hello' => 'world', 'test' => 'abc'), CookieParser::parse('hello=world; test=abc'));
    }

    public function testMultipleCookiesWithSameNameWillReturnLastValue()
    {
        $this->assertEquals(array('hello' => 'abc'), CookieParser::parse('hello=world; hello=abc'));
    }

    public function testOtherEqualSignsWillBeAddedToValueAndWillReturnValidArray()
    {
        $this->assertEquals(array('hello' => 'world=test=php'), CookieParser::parse('hello=world=test=php'));
    }

    public function testSingleCookieValueInCookiesReturnsEmptyArray()
    {
        $this->assertEquals(array(), CookieParser::parse('world'));
    }

    public function testSingleMutlipleCookieValuesReturnsEmptyArray()
    {
        $this->assertEquals(array(), CookieParser::parse('world; test'));
    }

    public function testSingleValueIsValidInMultipleValueCookieWillReturnValidArray()
    {
        $this->assertEquals(array('test' => 'php'), CookieParser::parse('world; test=php'));
    }

    public function testUrlEncodingForValueWillReturnValidArray()
    {
        $this->assertEquals(array('hello' => 'world!', 'test' => '100% coverage'), CookieParser::parse('hello=world%21; test=100%25%20coverage'));
    }

}
