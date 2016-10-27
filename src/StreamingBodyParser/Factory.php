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
        return RawBodyParser::create($request);
    }
}
