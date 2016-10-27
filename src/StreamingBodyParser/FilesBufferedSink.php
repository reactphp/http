<?php

namespace React\Http\StreamingBodyParser;

use React\Http\File;
use React\Promise;
use React\Stream\BufferedSink as StreamBufferedSink;

class FilesBufferedSink
{
    /**
     * @param ParserInterface $parser
     * @return Promise\PromiseInterface
     */
    public static function createPromise(ParserInterface $parser)
    {
        $deferred = new Promise\Deferred();
        $files = [];

        $parser->on('file', function ($name, File $file) use (&$files) {
            StreamBufferedSink::createPromise($file->getStream())->done(function ($buffer) use ($name, $file, &$files) {
                $files[] = [
                    'name' => $name,
                    'file' => $file,
                    'buffer' => $buffer,
                ];
            });
        });
        $parser->on('end', function () use ($deferred, &$files) {
            $deferred->resolve($files);
        });

        return $deferred->promise();
    }
}
