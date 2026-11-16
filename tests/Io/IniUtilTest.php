<?php

namespace React\Tests\Http\Io;

use React\Http\Io\IniUtil;
use React\Tests\Http\TestCase;

class IniUtilTest extends TestCase
{
    public function provideIniSizes()
    {
        return array(
            array(
                '1',
                1,
            ),
            array(
                '10',
                10,
            ),
            array(
                '1024',
                1024,
            ),
            array(
                '1K',
                1024,
            ),
            array(
                '1.5M',
                1572864,
            ),
            array(
                '64M',
                67108864,
            ),
            array(
                '8G',
                8589934592,
            ),
            array(
                '1T',
                1099511627776,
            ),
        );
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

    public function provideInvalidInputIniSizeToBytes()
    {
        return array(
            array('-1G'),
            array('0G'),
            array('foo'),
            array('fooK'),
            array('1ooL'),
            array('1ooL'),
        );
    }

    /**
     * @dataProvider provideInvalidInputIniSizeToBytes
     */
    public function testInvalidInputIniSizeToBytes($input)
    {
        $this->setExpectedException('InvalidArgumentException');
        IniUtil::iniSizeToBytes($input);
    }

    public function provideIniBooleansEnabled()
    {
        return array(
            array('1'),
            array('On'),
            array('on'),
            array('yes'),
            array('true'),
            array('TRUE'),
            array('2'),
            array('-1'),
            array('1abc'),
        );
    }

    /**
     * @dataProvider provideIniBooleansEnabled
     */
    public function testIniBooleanReturnsTrueForEnabledValue($input)
    {
        $this->assertTrue(IniUtil::iniBoolean($input));
    }

    public function provideIniBooleansDisabled()
    {
        return array(
            array(''),
            array('0'),
            array('Off'),
            array('off'),
            array('no'),
            array('false'),
            array('none'),
            array('00'),
            array('abc'),
        );
    }

    /**
     * @dataProvider provideIniBooleansDisabled
     */
    public function testIniBooleanReturnsFalseForDisabledValue($input)
    {
        $this->assertFalse(IniUtil::iniBoolean($input));
    }
}
