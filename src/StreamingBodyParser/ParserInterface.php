<?php

namespace React\Http\StreamingBodyParser;

use Evenement\EventEmitterInterface;
use React\Http\Request;

/**
 * Parser can emit the following events:
 *     end - When done or canceled (required)
 *     body - Raw request body chunks as they come in (optional)
 *     post - Post field (optional)
 *     file - Uploaded file (optional)
 */
interface ParserInterface extends EventEmitterInterface
{
    /**
     * Cancel parsing the request body stream.
     * Parser will still emit end event to any listeners.
     *
     * @return void
     */
    public function cancel();
}
