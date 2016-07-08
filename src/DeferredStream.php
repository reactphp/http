<?php

namespace React\Http;

use React\Http\StreamingBodyParser\NoBody;
use React\Http\StreamingBodyParser\StreamingParserInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Stream\BufferedSink;

class DeferredStream
{
    /**
     * @param StreamingParserInterface $parser
     * @return PromiseInterface
     */
    public static function create(StreamingParserInterface $parser)
    {
        if ($parser instanceof NoBody) {
            return \React\Promise\resolve([
                'post' => [],
                'files' => [],
                'body' => '',
            ]);
        }

        $deferred = new Deferred();
        $postFields = [];
        $files = [];
        $body = '';
        $parser->on('post', function ($key, $value) use (&$postFields) {
            self::extractPost($postFields, $key, $value);
        });
        $parser->on('file', function ($name, File $file) use (&$files) {
            BufferedSink::createPromise($file->getStream())->then(function ($buffer) use ($name, $file, &$files) {
                $files[] = [
                    'name' => $name,
                    'file' => $file,
                    'buffer' => $buffer,
                ];
            });
        });
        $parser->on('body', function ($rawBody) use (&$body) {
            $body = $rawBody;
        });
        $parser->on('end', function () use ($deferred, &$postFields, &$files, &$body) {
            $deferred->resolve([
                'post' => $postFields,
                'files' => $files,
                'body' => $body,
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
