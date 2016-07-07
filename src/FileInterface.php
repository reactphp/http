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
    public function getContentType();

    /**
     * @return ReadableStreamInterface
     */
    public function getStream();
}
