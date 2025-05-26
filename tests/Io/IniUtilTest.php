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

    public function testIniSizeViaEnvVariableWorks()
    {
        self::assertArrayNotHasKey('INIUTIL_ENV_VAR_WITH_PHP_SIZE_VALUE', $_ENV);
        $_ENV['INIUTIL_ENV_VAR_WITH_PHP_SIZE_VALUE'] = '23M';
        $this->assertSame(23 * 1024 * 1024, IniUtil::iniSizeToBytes('${INIUTIL_ENV_VAR_WITH_PHP_SIZE_VALUE}'));
    }

    public function testIniSizeViaSimpleEnvVariableWorks()
    {
        self::assertArrayNotHasKey('INIUTIL_SIMPLE_ENV_VAR_WITH_PHP_SIZE_VALUE', $_ENV);
        $_ENV['INIUTIL_SIMPLE_ENV_VAR_WITH_PHP_SIZE_VALUE'] = '42M';
        $this->assertSame(42 * 1024 * 1024, IniUtil::iniSizeToBytes('$INIUTIL_SIMPLE_ENV_VAR_WITH_PHP_SIZE_VALUE'));
    }

    public function testIniSizeViaEnvVariableFailsForNonSizeValue()
    {
        self::assertArrayNotHasKey('INIUTIL_ENV_VAR_WITHOUT_PHP_SIZE_VALUE', $_ENV);
        $_ENV['INIUTIL_ENV_VAR_WITHOUT_PHP_SIZE_VALUE'] = 'no-size';
        $this->expectException(\InvalidArgumentException::class);
        IniUtil::iniSizeToBytes('${INIUTIL_ENV_VAR_WITHOUT_PHP_SIZE_VALUE}');
    }

    public function testIniSizeViaEnvVariableIgnoresInvalidSizeModifier()
    {
        self::assertArrayNotHasKey('INIUTIL_ENV_VAR_WITH_PHP_SIZE_VALUE_AND_INVALID_MODIFIER', $_ENV);
        $_ENV['INIUTIL_ENV_VAR_WITH_PHP_SIZE_VALUE_AND_INVALID_MODIFIER'] = '1337V';
        $this->assertSame(1337, IniUtil::iniSizeToBytes('${INIUTIL_ENV_VAR_WITH_PHP_SIZE_VALUE_AND_INVALID_MODIFIER}'));
    }

    public function testIniSizeViaEnvVariableFailsForNonNumericValue()
    {
        self::assertArrayNotHasKey('INIUTIL_ENV_VAR_WITH_NON_NUMERIC_PHP_SIZE_VALUE', $_ENV);
        $_ENV['INIUTIL_ENV_VAR_WITH_NON_NUMERIC_PHP_SIZE_VALUE'] = 'V1337V';
        $this->expectException(\InvalidArgumentException::class);
        IniUtil::iniSizeToBytes('${INIUTIL_ENV_VAR_WITH_NON_NUMERIC_PHP_SIZE_VALUE}');
    }

    public function testIniSizeViaMissingEnvVariableFails()
    {
        self::assertArrayNotHasKey('INIUTIL_ENV_VAR_MISSING_WITH_NON_NUMERIC_PHP_SIZE_VALUE', $_ENV);
        $this->expectException(\InvalidArgumentException::class);
        IniUtil::iniSizeToBytes('${INIUTIL_ENV_VAR_MISSING_WITH_NON_NUMERIC_PHP_SIZE_VALUE}');
    }
}
