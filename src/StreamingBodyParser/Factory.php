<?php

namespace React\Http\StreamingBodyParser;

use React\Http\Request;

class Factory
{
    /**
     * @param Request $request
     * @return ParserInterface
     */
    public static function create(Request $request)
    {
        $headers = $request->getHeaders();
        $headers = array_change_key_case($headers, CASE_LOWER);

        if (
            !isset($headers['content-length'])
        ) {
            return NoBodyParser::create($request);
        }

        return RawBodyParser::create($request);
    }
}
