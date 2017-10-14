<?php

namespace React\Http;

interface RequestHeaderParserInterface
{

    /**
     * Feed the RequestHeaderParser with a data chunk from the connection
     * @param string $data
     * @return mixed
     */
    public function feed($data);
}
