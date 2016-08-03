<?php

namespace React\Http\StreamingBodyParser;

use Evenement\EventEmitterInterface;
use React\Http\Request;

interface ParserInterface extends EventEmitterInterface
{
    public function __construct(Request $request);
}
