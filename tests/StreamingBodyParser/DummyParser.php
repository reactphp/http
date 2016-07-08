<?php

namespace React\Tests\Http\StreamingBodyParser;

use Evenement\EventEmitterTrait;
use React\Http\StreamingBodyParser\StreamingParserInterface;
use React\Http\Request;

class DummyParser implements StreamingParserInterface
{
    use EventEmitterTrait;

    public function __construct(Request $request)
    {
    }
}
