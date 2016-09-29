<?php

namespace React\Http\StreamingBodyParser;

use Evenement\EventEmitterInterface;
use React\Http\Request;

/**
 * Parser can emit the following events:
 *     end - When done or canceled (required)
 *     body - Raw request body (optional)
 *     post - Post field (optional)
 *     file - Uploaded file (optional)
 */
interface ParserInterface extends EventEmitterInterface
{
    /**
     * Factory method creating the parser.
     *
     * @param Request $request
     * @return ParserInterface
     */
    public static function create(Request $request);

    /**
     * Cancel parsing the request body stream.
     * Parser will still emit end event to any listeners.
     *
     * @return void
     */
    public function cancel();
}
