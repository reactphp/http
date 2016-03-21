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

        if (strpos($headers['Content-Type'], 'multipart/') === 0) {
            return new Multipart($request);
        }

        if (strtolower($headers['Content-Type']) == 'application/x-www-form-urlencoded') {
            return new FormUrlencoded($request);
        }
    }
}
