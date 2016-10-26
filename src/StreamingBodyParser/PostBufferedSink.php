<?php

namespace React\Http\StreamingBodyParser;

use React\Promise;

class PostBufferedSink
{
    /**
     * @param ParserInterface $parser
     * @return PromiseInterface
     */
    public static function createPromise(ParserInterface $parser)
    {
        if ($parser instanceof NoBodyParser) {
            return Promise\resolve([]);
        }

        $deferred = new Promise\Deferred();
        $postFields = [];

        $parser->on('post', function ($key, $value) use (&$postFields) {
            self::extractPost($postFields, $key, $value);
        });
        $parser->on('end', function () use ($deferred, &$postFields) {
            $deferred->resolve($postFields);
        });

        return $deferred->promise();
    }

    public static function extractPost(&$postFields, $key, $value)
    {
        $chunks = explode('[', $key);
        if (count($chunks) == 1) {
            $postFields[$key] = $value;
            return;
        }

        $chunkKey = $chunks[0];
        if (!isset($postFields[$chunkKey])) {
            $postFields[$chunkKey] = [];
        }

        $parent = &$postFields;
        for ($i = 1; $i < count($chunks); $i++) {
            $previousChunkKey = $chunkKey;
            if (!isset($parent[$previousChunkKey])) {
                $parent[$previousChunkKey] = [];
            }
            $parent = &$parent[$previousChunkKey];
            $chunkKey = $chunks[$i];

            if ($chunkKey == ']') {
                $parent[] = $value;
                return;
            }

            $chunkKey = rtrim($chunkKey, ']');
            if ($i == count($chunks) - 1) {
                $parent[$chunkKey] = $value;
                return;
            }
        }
    }
}
