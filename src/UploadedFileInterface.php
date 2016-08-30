<?php

namespace React\Http;

use React\Stream\ReadableStreamInterface;

interface UploadedFileInterface
{
    /**
     * @return string
     */
    public function getClientFilename();

    /**
     * @return string
     */
    public function getClientMediaType();

    /**
     * @return ReadableStreamInterface
     */
    public function getStream();
}
