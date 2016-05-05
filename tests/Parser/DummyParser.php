<?php

namespace React\Tests\Http\Parser;

use Evenement\EventEmitterTrait;
use React\Http\Parser\DoneTrait;
use React\Http\Parser\ParserInterface;
use React\Http\Request;

class DummyParser implements ParserInterface
{
    use EventEmitterTrait;
    use DoneTrait;

    public function __construct(Request $request)
    {
    }

    public function setDone()
    {
        $this->markDone();
    }
}
