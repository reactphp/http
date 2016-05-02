<?php

namespace React\Http\Parser;

use Evenement\EventEmitterTrait;
use React\Http\Request;
use React\Stream\Util;

class NoBody implements ParserInterface
{
    use EventEmitterTrait;

    public function __construct(Request $request)
    {
        Util::forwardEvents($request, $this, [
            'end',
        ]);
    }
}
