<?php

namespace React\Http\StreamingBodyParser;

use Evenement\EventEmitterTrait;
use React\Http\Request;

class NoBodyParser implements ParserInterface
{
    use EventEmitterTrait;

    public function __construct(Request $request)
    {
    }
}
