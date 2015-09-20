<?php

namespace React\Http;

use Evenement\EventEmitterInterface;

interface RequestParserInterface extends EventEmitterInterface {

    /**
     * @param $data
     * @return null
     */
    public function feed($data);

}