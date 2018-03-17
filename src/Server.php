<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Http\Io\IniUtil;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Middleware\RequestBodyParserMiddleware;
use React\Socket\ServerInterface;

/**
 * Facade around StreamingServer with sane defaults for 80% of the use cases.
 * The requests passed to your callable are fully compatible with PSR-7 because
 * the body of the requests are fully buffered and parsed, unlike StreamingServer
 * where the body is a raw ReactPHP stream.
 *
 * Wraps StreamingServer with the following middleware:
 * - LimitConcurrentRequestsMiddleware
 * - RequestBodyBufferMiddleware
 * - RequestBodyParserMiddleware (only when enable_post_data_reading is true (default))
 * - The callable you in passed as first constructor argument
 *
 * All middleware use their default configuration, which can be controlled with
 * the the following configuration directives from php.ini:
 * - upload_max_filesize
 * - post_max_size
 * - max_input_nesting_level
 * - max_input_vars
 * - file_uploads
 * - max_file_uploads
 * - enable_post_data_reading
 *
 * Forwards the error event coming from StreamingServer.
 */
final class Server extends EventEmitter
{
    /**
     * @internal
     */
    const MAXIMUM_CONCURRENT_REQUESTS = 100;

    /**
     * @var StreamingServer
     */
    private $streamingServer;

    /**
     * @see StreamingServer::__construct()
     */
    public function __construct($requestHandler)
    {
        if (!is_callable($requestHandler) && !is_array($requestHandler)) {
            throw new \InvalidArgumentException('Invalid request handler given');
        }

        $middleware = array();
        $middleware[] = new LimitConcurrentRequestsMiddleware($this->getConcurrentRequestsLimit());
        $middleware[] = new RequestBodyBufferMiddleware();
        // Checking for an empty string because that is what a boolean
        // false is returned as by ini_get depending on the PHP version.
        // @link http://php.net/manual/en/ini.core.php#ini.enable-post-data-reading
        // @link http://php.net/manual/en/function.ini-get.php#refsect1-function.ini-get-notes
        // @link https://3v4l.org/qJtsa
        $enablePostDataReading = ini_get('enable_post_data_reading');
        if ($enablePostDataReading !== '') {
            $middleware[] = new RequestBodyParserMiddleware();
        }

        if (is_callable($requestHandler)) {
            $middleware[] = $requestHandler;
        } else {
            $middleware = array_merge($middleware, $requestHandler);
        }

        $this->streamingServer = new StreamingServer($middleware);

        $that = $this;
        $this->streamingServer->on('error', function ($error) use ($that) {
            $that->emit('error', array($error));
        });
    }

    /**
     * @see StreamingServer::listen()
     */
    public function listen(ServerInterface $server)
    {
        $this->streamingServer->listen($server);
    }

    /**
     * @return int
     * @codeCoverageIgnore
     */
    private function getConcurrentRequestsLimit()
    {
        if (ini_get('memory_limit') == -1) {
            return self::MAXIMUM_CONCURRENT_REQUESTS;
        }

        $availableMemory = IniUtil::iniSizeToBytes(ini_get('memory_limit')) / 4;
        $concurrentRequests = ceil($availableMemory / IniUtil::iniSizeToBytes(ini_get('post_max_size')));

        if ($concurrentRequests >= self::MAXIMUM_CONCURRENT_REQUESTS) {
            return self::MAXIMUM_CONCURRENT_REQUESTS;
        }

        return $concurrentRequests;
    }
}
