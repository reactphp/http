<?php

namespace React\Tests\Http\BodyParser;

use Evenement\EventEmitterTrait;
use React\Http\BodyParser\ParserInterface;
use React\Http\Request;

class DummyParser implements ParserInterface
{
    use EventEmitterTrait;

    public function __construct(Request $request)
    {
    }
}
