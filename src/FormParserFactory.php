<?php

namespace React\Http;

use React\Http\Parser;

class FormParserFactory
{
    /**
     * @param Request $request
     * @return Parser\ParserInterface
     */
    public static function create(Request $request)
    {
        $headers = $request->getHeaders();
        $headers = array_change_key_case($headers, CASE_LOWER);

        if (
            !isset($headers['content-type']) ||
            (!isset($headers['content-type']) && !isset($headers['content-length']))
        ) {
            return new Parser\NoBody($request);
        }

        $contentType = strtolower($headers['content-type']);

        if (strpos($contentType, 'multipart/') === 0) {
            return new Parser\Multipart($request);
        }

        if (strpos($contentType, 'application/x-www-form-urlencoded') === 0) {
            return new Parser\FormUrlencoded($request);
        }

        return new Parser\RawBody($request);
    }
}
