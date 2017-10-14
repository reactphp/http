<?php

namespace React\Http;

use Evenement\EventEmitterInterface;

interface RequestHeaderParserInterface extends EventEmitterInterface
{

    /**
     * Feed the RequestHeaderParser with a data chunk from the connection
     * @param string $data
     * @return void
     */
    public function feed($data);
}
