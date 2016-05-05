<?php

namespace React\Http\Parser;

use Evenement\EventEmitterInterface;
use React\Http\Request;

interface ParserInterface extends EventEmitterInterface
{
    public function __construct(Request $request);

    /**
     * @return bool
     */
    public function isDone();
}
