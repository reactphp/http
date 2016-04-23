<?php

namespace React\Http;

use React\Http\Parser\FormUrlencoded;
use React\Http\Parser\Multipart;
use React\Http\Parser\NoBody;
use React\Http\Parser\ParserInterface;

class FormParserFactory
{
    /**
     * @param Request $request
     * @return ParserInterface
     */
    public static function create(Request $request)
    {
        $headers = $request->getHeaders();
        $headers = array_change_key_case($headers, CASE_LOWER);

        if (!array_key_exists('content-type', $headers)) {
            return new NoBody($request);
        }

        $contentType = strtolower($headers['content-type']);

        if (strpos($contentType, 'multipart/') === 0) {
            return new Multipart($request);
        }

        if (strpos($contentType, 'application/x-www-form-urlencoded') === 0) {
            return new FormUrlencoded($request);
        }

        return new NoBody($request);
    }
}
