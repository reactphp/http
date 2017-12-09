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

    public function provideInvalidInputIniSizeToBytes()
    {
        return array(
            array('-1G'),
            array('0G'),
            array(null),
            array('foo'),
            array('fooK'),
            array('1ooL'),
            array('1ooL'),
        );
    }

    /**
     * @dataProvider provideInvalidInputIniSizeToBytes
     * @expectedException InvalidArgumentException
     */
    public function testInvalidInputIniSizeToBytes($input)
    {
        IniUtil::iniSizeToBytes($input);
    }
}
