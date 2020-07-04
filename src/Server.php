<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Http\Io\IniUtil;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Middleware\RequestBodyParserMiddleware;
use React\Socket\ServerInterface;

/**
 * The `Server` class is responsible for handling incoming connections and then
 * processing each incoming HTTP request.
 *
 * It buffers and parses the complete incoming HTTP request in memory. Once the
 * complete request has been received, it will invoke the request handler function.
 * This request handler function needs to be passed to the constructor and will be
 * invoked with the respective [request](#request) object and expects a
 * [response](#response) object in return:
 *
 * ```php
 * $server = new Server(function (ServerRequestInterface $request) {
 *     return new Response(
 *         200,
 *         array(
 *             'Content-Type' => 'text/plain'
 *         ),
 *         "Hello World!\n"
 *     );
 * });
 * ```
 *
 * Each incoming HTTP request message is always represented by the
 * [PSR-7 `ServerRequestInterface`](https://www.php-fig.org/psr/psr-7/#321-psrhttpmessageserverrequestinterface),
 * see also following [request](#request) chapter for more details.
 * Each outgoing HTTP response message is always represented by the
 * [PSR-7 `ResponseInterface`](https://www.php-fig.org/psr/psr-7/#33-psrhttpmessageresponseinterface),
 * see also following [response](#response) chapter for more details.
 *
 * In order to process any connections, the server needs to be attached to an
 * instance of `React\Socket\ServerInterface` through the [`listen()`](#listen) method
 * as described in the following chapter. In its most simple form, you can attach
 * this to a [`React\Socket\Server`](https://github.com/reactphp/socket#server)
 * in order to start a plaintext HTTP server like this:
 *
 * ```php
 * $server = new Server($handler);
 *
 * $socket = new React\Socket\Server('0.0.0.0:8080', $loop);
 * $server->listen($socket);
 * ```
 *
 * See also the [`listen()`](#listen) method and the [first example](examples) for more details.
 *
 * The `Server` class is built as a facade around the underlying
 * [`StreamingServer`](#streamingserver) to provide sane defaults for 80% of the
 * use cases and is the recommended way to use this library unless you're sure
 * you know what you're doing.
 *
 * Unlike the underlying [`StreamingServer`](#streamingserver), this class
 * buffers and parses the complete incoming HTTP request in memory. Once the
 * complete request has been received, it will invoke the request handler
 * function. This means the [request](#request) passed to your request handler
 * function will be fully compatible with PSR-7.
 *
 * On the other hand, buffering complete HTTP requests in memory until they can
 * be processed by your request handler function means that this class has to
 * employ a number of limits to avoid consuming too much memory. In order to
 * take the more advanced configuration out your hand, it respects setting from
 * your [`php.ini`](https://www.php.net/manual/en/ini.core.php) to apply its
 * default settings. This is a list of PHP settings this class respects with
 * their respective default values:
 *
 * ```
 * memory_limit 128M
 * post_max_size 8M
 * enable_post_data_reading 1
 * max_input_nesting_level 64
 * max_input_vars 1000
 *
 * file_uploads 1
 * upload_max_filesize 2M
 * max_file_uploads 20
 * ```
 *
 * In particular, the `post_max_size` setting limits how much memory a single HTTP
 * request is allowed to consume while buffering its request body. On top of
 * this, this class will try to avoid consuming more than 1/4 of your
 * `memory_limit` for buffering multiple concurrent HTTP requests. As such, with
 * the above default settings of `128M` max, it will try to consume no more than
 * `32M` for buffering multiple concurrent HTTP requests. As a consequence, it
 * will limit the concurrency to 4 HTTP requests with the above defaults.
 *
 * It is imperative that you assign reasonable values to your PHP ini settings.
 * It is usually recommended to either reduce the memory a single request is
 * allowed to take (set `post_max_size 1M` or less) or to increase the total memory
 * limit to allow for more concurrent requests (set `memory_limit 512M` or more).
 * Failure to do so means that this class may have to disable concurrency and
 * only handle one request at a time.
 *
 * Internally, this class automatically assigns these limits to the
 * [middleware](#middleware) request handlers as described below. For more
 * advanced use cases, you may also use the advanced
 * [`StreamingServer`](#streamingserver) and assign these middleware request
 * handlers yourself as described in the following chapters.
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
        if (!\is_callable($requestHandler) && !\is_array($requestHandler)) {
            throw new \InvalidArgumentException('Invalid request handler given');
        }

        $middleware = array();
        $middleware[] = new LimitConcurrentRequestsMiddleware(
            $this->getConcurrentRequestsLimit(\ini_get('memory_limit'), \ini_get('post_max_size'))
        );
        $middleware[] = new RequestBodyBufferMiddleware();
        // Checking for an empty string because that is what a boolean
        // false is returned as by ini_get depending on the PHP version.
        // @link http://php.net/manual/en/ini.core.php#ini.enable-post-data-reading
        // @link http://php.net/manual/en/function.ini-get.php#refsect1-function.ini-get-notes
        // @link https://3v4l.org/qJtsa
        $enablePostDataReading = \ini_get('enable_post_data_reading');
        if ($enablePostDataReading !== '') {
            $middleware[] = new RequestBodyParserMiddleware();
        }

        if (\is_callable($requestHandler)) {
            $middleware[] = $requestHandler;
        } else {
            $middleware = \array_merge($middleware, $requestHandler);
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
     * @param string $memory_limit
     * @param string $post_max_size
     * @return int
     */
    private function getConcurrentRequestsLimit($memory_limit, $post_max_size)
    {
        if ($memory_limit == -1) {
            return self::MAXIMUM_CONCURRENT_REQUESTS;
        }

        if ($post_max_size == 0) {
            return 1;
        }

        $availableMemory = IniUtil::iniSizeToBytes($memory_limit) / 4;
        $concurrentRequests = (int) \ceil($availableMemory / IniUtil::iniSizeToBytes($post_max_size));

        if ($concurrentRequests >= self::MAXIMUM_CONCURRENT_REQUESTS) {
            return self::MAXIMUM_CONCURRENT_REQUESTS;
        }

        return $concurrentRequests;
    }
}
