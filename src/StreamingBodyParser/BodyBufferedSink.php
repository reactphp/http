<?php

namespace React\Http\StreamingBodyParser;

use React\Http\File;
use React\Promise;

class BodyBufferedSink
{
    /**
     * @param ParserInterface $parser
     * @return PromiseInterface
     */
    public static function createPromise(ParserInterface $parser)
    {
        if ($parser instanceof NoBodyParser) {
            return Promise\resolve('');
        }

        $deferred = new Promise\Deferred();
        $body = '';

        $parser->on('body', function ($rawBody) use (&$body) {
            $body = $rawBody;
        });
        $parser->on('end', function () use ($deferred, &$postFields, &$files, &$body) {
            $deferred->resolve($body);
        });

        return $deferred->promise();
    }
}