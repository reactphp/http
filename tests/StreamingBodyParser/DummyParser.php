<?php

namespace React\Tests\Http\StreamingBodyParser;

use Evenement\EventEmitterTrait;
use React\Http\StreamingBodyParser\ParserInterface;
use React\Http\Request;

class DummyParser implements ParserInterface
{
    use EventEmitterTrait;

    public function __construct(Request $request)
    {
    }
}
