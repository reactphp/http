<?php

namespace React\Http\Parser;

trait DoneTrait
{
    private $done = false;

    public function isDone()
    {
        return $this->done;
    }

    protected function markDone()
    {
        $this->done = true;
    }
}
