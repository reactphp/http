<?php

namespace React\Http;

use Evenement\EventEmitterInterface;

/**
 * @event headers
 * @event error
 */
interface RequestParserInterface extends EventEmitterInterface {

    /**
     * @param $data
     * @return null
     */
    public function feed($data);

}