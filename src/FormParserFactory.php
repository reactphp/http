<?php

namespace React\Http;

use React\Http\Parser\FormUrlencoded;
use React\Http\Parser\Multipart;

class FormParserFactory
{
    public static function create(Request $request)
    {
        $headers = $request->getHeaders();

        if (!array_key_exists('Content-Type', $headers)) {
        }

        $contentType = strtolower($headers['Content-Type']);

        if (strpos($contentType, 'multipart/') === 0) {
            return new Multipart($request);
        }

        if (strpos($contentType, 'application/x-www-form-urlencoded') === 0) {
            return new FormUrlencoded($request);
        }
    }
}
