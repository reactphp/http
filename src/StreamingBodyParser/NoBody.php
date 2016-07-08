<?php

namespace React\Http\StreamingBodyParser;

use Evenement\EventEmitterTrait;
use React\Http\Request;

class NoBody implements StreamingParserInterface
{
    use EventEmitterTrait;

    public function __construct(Request $request)
    {
    }
}
