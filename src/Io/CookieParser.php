<?php

namespace React\Http\Io;

/**
 * @internal
 */
final class CookieParser
{
    /**
     * @param string $cookie
     * @return array
     */
    public static function parse($cookie)
    {
        $cookieArray = \explode(';', $cookie);
        $result = array();

        foreach ($cookieArray as $pair) {
            $pair = \trim($pair);
            $nameValuePair = \explode('=', $pair, 2);

            if (\count($nameValuePair) === 2) {
                $key = \urldecode($nameValuePair[0]);
                $value = \urldecode($nameValuePair[1]);
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
