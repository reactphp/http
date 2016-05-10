<?php

namespace React\Tests\Http\Parser;

use Evenement\EventEmitterTrait;
use React\Http\Parser\ParserInterface;
use React\Http\Request;

class DummyParser implements ParserInterface
{
    use EventEmitterTrait;

    public function __construct(Request $request)
    {
    }
}
