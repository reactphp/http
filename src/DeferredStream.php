<?php

namespace React\Http;

use React\Http\Parser\ParserInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Stream\BufferedSink;

class DeferredStream
{
    /**
     * @param ParserInterface $parser
     * @return PromiseInterface
     */
    public static function create(ParserInterface $parser)
    {
        $deferred = new Deferred();
        $postFields = [];
        $files = [];
        $parser->on('post', function ($key, $value) use (&$postFields) {
            self::extractPost($postFields, $key, $value);
        });
        $parser->on('file', function (File $file) use (&$files) {
            BufferedSink::createPromise($file->getStream())->then(function ($buffer) use ($file, &$files) {
                $files[] = [
                    'file' => $file,
                    'buffer' => $buffer,
                ];
            });
        });
        $parser->on('end', function () use ($deferred, &$postFields, &$files) {
            $deferred->resolve([
                'post' => $postFields,
                'files' => $files,
            ]);
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
