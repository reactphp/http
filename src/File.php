<?php

namespace React\Http;

use React\Stream\ReadableStreamInterface;

class File implements FileInterface
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $filename;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var ReadableStreamInterface
     */
    protected $stream;

    /**
     * @param string $name
     * @param string $filename
     * @param string $type
     * @param ReadableStreamInterface $stream
     */
    public function __construct($name, $filename, $type, ReadableStreamInterface $stream)
    {
        $this->name = $name;
        $this->filename = $filename;
        $this->type = $type;
        $this->stream = $stream;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return ReadableStreamInterface
     */
    public function getStream()
    {
        return $this->stream;
    }
}
