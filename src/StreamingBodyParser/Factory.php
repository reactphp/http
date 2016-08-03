<?php

namespace React\Http\StreamingBodyParser;

use React\Http\Request;

class Factory
{
    /**
     * @param Request $request
     * @return StreamingParserInterface
     */
    public static function create(Request $request)
    {
        $headers = $request->getHeaders();
        $headers = array_change_key_case($headers, CASE_LOWER);

        if (
            !isset($headers['content-type']) && !isset($headers['content-length'])
        ) {
            return new NoBodyParser($request);
        }

        $contentType = strtolower($headers['content-type']);

        if (strpos($contentType, 'multipart/') === 0) {
            return new MultipartParser($request);
        }

        if (strpos($contentType, 'application/x-www-form-urlencoded') === 0) {
            return new FormUrlencodedParser($request);
        }

        return new RawBodyParser($request);
    }
}
