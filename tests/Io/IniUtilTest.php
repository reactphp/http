<?php

namespace React\Tests\Http\Io;

use React\Http\Io\IniUtil;
use React\Tests\Http\TestCase;

class IniUtilTest extends TestCase
{
    public static function provideIniSizes()
    {
        yield [
            '1',
            1,
        ];
        yield [
            '10',
            10,
        ];
        yield [
            '1024',
            1024,
        ];
        yield [
            '1K',
            1024,
        ];
        yield [
            '1.5M',
            1572864,
        ];
        yield [
            '64M',
            67108864,
        ];
        yield [
            '8G',
            8589934592,
        ];
        yield [
            '1T',
            1099511627776,
        ];
    }

    /**
     * @dataProvider provideIniSizes
     */
    public function testIniSizeToBytes($input, $output)
    {
        $this->assertEquals($output, IniUtil::iniSizeToBytes($input));
    }

    public function testIniSizeToBytesWithInvalidSuffixReturnsNumberWithoutSuffix()
    {
        $this->assertEquals('2', IniUtil::iniSizeToBytes('2x'));
    }

    public static function provideInvalidInputIniSizeToBytes()
    {
        yield ['-1G'];
        yield ['0G'];
        yield ['foo'];
        yield ['fooK'];
        yield ['1ooL'];
        yield ['1ooL'];
    }

    /**
     * @dataProvider provideInvalidInputIniSizeToBytes
     */
    public function testInvalidInputIniSizeToBytes($input)
    {
        $this->expectException(\InvalidArgumentException::class);
        IniUtil::iniSizeToBytes($input);
    }

    public static function provideIniBooleansEnabled()
    {
        yield ['1'];
        yield ['On'];
        yield ['on'];
        yield ['yes'];
        yield ['true'];
        yield ['TRUE'];
        yield ['2'];
        yield ['-1'];
        yield ['1abc'];
    }

    /**
     * @dataProvider provideIniBooleansEnabled
     */
    public function testIniBooleanReturnsTrueForEnabledValue($input)
    {
        $this->assertTrue(IniUtil::iniBoolean($input));
    }

    public static function provideIniBooleansDisabled()
    {
        yield [''];
        yield ['0'];
        yield ['Off'];
        yield ['off'];
        yield ['no'];
        yield ['false'];
        yield ['none'];
        yield ['00'];
        yield ['abc'];
    }

    /**
     * @dataProvider provideIniBooleansDisabled
     */
    public function testIniBooleanReturnsFalseForDisabledValue($input)
    {
        $this->assertFalse(IniUtil::iniBoolean($input));
    }
}
