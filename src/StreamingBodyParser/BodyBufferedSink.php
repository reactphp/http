<?php

namespace React\Http\StreamingBodyParser;

use React\Http\File;
use React\Promise;

class BodyBufferedSink
{
    /**
     * @param ParserInterface $parser
     * @return Promise\PromiseInterface
     */
    public static function createPromise(ParserInterface $parser)
    {
        $deferred = new Promise\Deferred();
        $body = '';

        $parser->on('body', function ($rawBody) use (&$body) {
            $body .= $rawBody;
        });
        $parser->on('end', function () use ($deferred, &$body) {
            $deferred->resolve($body);
        });

        return $deferred->promise();
    }
}