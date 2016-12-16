<?php

namespace React\Http\StreamingBodyParser;

use React\Http\Request;

class Factory
{
    /**
     * Creates the most suitable streaming body parser for the given request.
     * It can return the following parsers:
     *   * RawBodyParser - Emit raw body chunks as they come in. This is the default parser.
     *
     * @param Request $request
     * @return ParserInterface
     */
    public static function create(Request $request)
    {
        return new RawBodyParser($request);
    }
}
