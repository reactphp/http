<?php

namespace React\Http;

use React\Stream\ReadableStreamInterface;

interface FileInterface
{
    /**
     * @return string
     */
    public function getName();

    /**
     * @return string
     */
    public function getFilename();

    /**
     * @return string
     */
    public function getType();

    /**
     * @return ReadableStreamInterface
     */
    public function getStream();
}
