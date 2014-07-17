<?php

namespace React\Http;

use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;
use React\Stream\Stream;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\File;

class StaticServer extends Server
{

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param ServerInterface $io
     * @param LoopInterface   $loop
     * @param array           $options
     */
    public function __construct(ServerInterface $io, LoopInterface $loop, array $options = [])
    {
        parent::__construct($io);

        $this->loop    = $loop;
        $this->options = $options;

        $this->on('request', function (Request $request, Response $response) use ($loop, $options) {

            $path = $this->getFilePath($request);
            $file = $this->getFile($path);

            if ($file) {
                $type = $file->getMimeType();

                $response->writeHead(200, ['Content-type' => $type]);

                $stream = $this->getFileStream($path, $loop);

                $stream->on('data', function () use ($loop, $stream, &$timer) {
                    $loop->cancelTimer($timer);
                    
                    $timer = $this->getTimeoutTimer($loop, $stream);

                    // TODO: log
                });

                $stream->on('end', function () use ($loop, $response, &$timer) {
                    $loop->cancelTimer($timer);
                    $response->end();

                    // TODO: log
                });

                $stream->on('error', function () use ($loop, $response, &$timer) {
                    $loop->cancelTimer($timer);
                    $response->end();

                    // TODO: log
                });

                $stream->on('close', function () use ($loop, $response, &$timer) {
                    $loop->cancelTimer($timer);
                    $response->end();

                    // TODO: log
                });

                $timer = $this->getTimeoutTimer($loop, $stream);

                $stream->pipe($response);

                // TODO: log

            } else {
                $this->respondWithNotFound($response);
            }
        });
    }

    /**
     * @param string $path
     *
     * @return File
     */
    protected function getFile($path)
    {
        try {
            return new File($path);
        } catch (FileNotFoundException $e) {
            // TODO: log
        }
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    protected function getFilePath(Request $request)
    {
        $path = __DIR__;

        if (isset($this->options['path'])) {
            $path = $this->options['path'];
        }

        return $path . '/' . $request->getPath();
    }

    /**
     * @param string        $path
     * @param LoopInterface $loop
     *
     * @return Stream
     */
    protected function getFileStream($path, LoopInterface $loop)
    {
        $resource = fopen($path, 'r');
        return new Stream($resource, $loop);
    }

    /**
     * @return int
     */
    protected function getTimeout()
    {
        if (isset($this->options['timeout'])) {
            return $this->options['timeout'];
        }

        return 5;
    }

    /**
     * @param LoopInterface $loop
     * @param Stream        $stream
     *
     * @return mixed
     */
    protected function getTimeoutTimer($loop, $stream)
    {
        $timeout = $this->getTimeout();

        return $loop->addTimer($timeout, function () use ($stream) {
            $stream->close();

            // TODO: log
        });
    }

    /**
     * @param Response $response
     */
    protected function respondWithNotFound(Response $response)
    {
        $type    = $this->getNotFoundContentType();
        $content = $this->getNotFoundContent();

        $response->writeHead(404, ['Content-type' => $type]);
        $response->end($content);

        // TODO: log
    }

    /**
     * @return string
     */
    protected function getNotFoundContentType()
    {
        if (isset($this->options['not-found-content-type'])) {
            return $this->options['not-found-content-type'];
        }

        return 'text/plain';
    }

    /**
     * @return string
     */
    protected function getNotFoundContent()
    {
        if (isset($this->options['not-found-content'])) {
            return $this->options['not-found-content'];
        }

        return 'not found';
    }

}
