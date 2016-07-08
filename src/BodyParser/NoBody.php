<?php

namespace React\Http\BodyParser;

use Evenement\EventEmitterTrait;
use React\Http\Request;

class NoBody implements ParserInterface
{
    use EventEmitterTrait;

    public function __construct(Request $request)
    {
    }
}
