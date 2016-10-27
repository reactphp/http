<?php

namespace React\Http\StreamingBodyParser;

use React\Promise;

class BufferedSink
{
    /**
     * @param ParserInterface $parser
     * @return Promise\PromiseInterface
     */
    public static function createPromise(ParserInterface $parser)
    {
        $promises = [
            'body'  => BodyBufferedSink::createPromise($parser),
            'post'  => PostBufferedSink::createPromise($parser),
            'files' => FilesBufferedSink::createPromise($parser),
        ];

        return Promise\all($promises);
    }
}
