<?php

namespace React\Http;

use Evenement\EventEmitter;
use React\Http\Io\IniUtil;
use React\Http\Io\StreamingServer;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\StreamingRequestMiddleware;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Middleware\RequestBodyParserMiddleware;
use React\Socket\ServerInterface;

/**
 * The `Server` class is responsible for handling incoming connections and then
 * processing each incoming HTTP request.
 *
 * When a complete HTTP request has been received, it will invoke the given
 * request handler function. This request handler function needs to be passed to
 * the constructor and will be invoked with the respective [request](#request)
 * object and expects a [response](#response) object in return:
 *
 * ```php
 * $server = new React\Http\Server(function (Psr\Http\Message\ServerRequestInterface $request) {
 *     return new React\Http\Response(
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
 *
 * Each outgoing HTTP response message is always represented by the
 * [PSR-7 `ResponseInterface`](https://www.php-fig.org/psr/psr-7/#33-psrhttpmessageresponseinterface),
 * see also following [response](#response) chapter for more details.
 *
 * In order to start listening for any incoming connections, the `Server` needs
 * to be attached to an instance of
 * [`React\Socket\ServerInterface`](https://github.com/reactphp/socket#serverinterface)
 * through the [`listen()`](#listen) method as described in the following
 * chapter. In its most simple form, you can attach this to a
 * [`React\Socket\Server`](https://github.com/reactphp/socket#server) in order
 * to start a plaintext HTTP server like this:
 *
 * ```php
 * $server = new React\Http\Server($handler);
 *
 * $socket = new React\Socket\Server('0.0.0.0:8080', $loop);
 * $server->listen($socket);
 * ```
 *
 * See also the [`listen()`](#listen) method and the [first example](../examples/)
 * for more details.
 *
 * By default, the `Server` buffers and parses the complete incoming HTTP
 * request in memory. It will invoke the given request handler function when the
 * complete request headers and request body has been received. This means the
 * [request](#request) object passed to your request handler function will be
 * fully compatible with PSR-7 (http-message). This provides sane defaults for
 * 80% of the use cases and is the recommended way to use this library unless
 * you're sure you know what you're doing.
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
 * In particular, the `post_max_size` setting limits how much memory a single
 * HTTP request is allowed to consume while buffering its request body. On top
 * of this, this class will try to avoid consuming more than 1/4 of your
 * `memory_limit` for buffering multiple concurrent HTTP requests. As such, with
 * the above default settings of `128M` max, it will try to consume no more than
 * `32M` for buffering multiple concurrent HTTP requests. As a consequence, it
 * will limit the concurrency to 4 HTTP requests with the above defaults.
 *
 * It is imperative that you assign reasonable values to your PHP ini settings.
 * It is usually recommended to either reduce the memory a single request is
 * allowed to take (set `post_max_size 1M` or less) or to increase the total
 * memory limit to allow for more concurrent requests (set `memory_limit 512M`
 * or more). Failure to do so means that this class may have to disable
 * concurrency and only handle one request at a time.
 *
 * As an alternative to the above buffering defaults, you can also configure
 * the `Server` explicitly to override these defaults. You can use the
 * [`LimitConcurrentRequestsMiddleware`](#limitconcurrentrequestsmiddleware) and
 * [`RequestBodyBufferMiddleware`](#requestbodybuffermiddleware) (see below)
 * to explicitly configure the total number of requests that can be handled at
 * once like this:
 *
 * ```php
 * $server = new React\Http\Server(array(
 *     new React\Http\Middleware\StreamingRequestMiddleware(),
 *     new React\Http\Middleware\LimitConcurrentRequestsMiddleware(100), // 100 concurrent buffering handlers
 *     new React\Http\Middleware\RequestBodyBufferMiddleware(2 * 1024 * 1024), // 2 MiB per request
 *     new React\Http\Middleware\RequestBodyParserMiddleware(),
 *     $handler
 * ));
 * ```
 *
 * > Internally, this class automatically assigns these middleware handlers
 *   automatically when no [`StreamingRequestMiddleware`](#streamingrequestmiddleware)
 *   is given. Accordingly, you can use this example to override all default
 *   settings to implement custom limits.
 *
 * As an alternative to buffering the complete request body in memory, you can
 * also use a streaming approach where only small chunks of data have to be kept
 * in memory:
 *
 * ```php
 * $server = new React\Http\Server(array(
 *     new React\Http\Middleware\StreamingRequestMiddleware(),
 *     $handler
 * ));
 * ```
 *
 * In this case, it will invoke the request handler function once the HTTP
 * request headers have been received, i.e. before receiving the potentially
 * much larger HTTP request body. This means the [request](#request) passed to
 * your request handler function may not be fully compatible with PSR-7. This is
 * specifically designed to help with more advanced use cases where you want to
 * have full control over consuming the incoming HTTP request body and
 * concurrency settings. See also [streaming incoming request](#streaming-incoming-request)
 * below for more details.
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
     * Creates an HTTP server that invokes the given callback for each incoming HTTP request
     *
     * In order to process any connections, the server needs to be attached to an
     * instance of `React\Socket\ServerInterface` which emits underlying streaming
     * connections in order to then parse incoming data as HTTP.
     * See also [listen()](#listen) for more details.
     *
     * @param callable|callable[] $requestHandler
     * @see self::listen()
     */
    public function __construct($requestHandler)
    {
        if (!\is_callable($requestHandler) && !\is_array($requestHandler)) {
            throw new \InvalidArgumentException('Invalid request handler given');
        }

        $streaming = false;
        foreach ((array) $requestHandler as $handler) {
            if ($handler instanceof StreamingRequestMiddleware) {
                $streaming = true;
                break;
            }
        }

        $middleware = array();
        if (!$streaming) {
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
     * Starts listening for HTTP requests on the given socket server instance
     *
     * The given [`React\Socket\ServerInterface`](https://github.com/reactphp/socket#serverinterface)
     * is responsible for emitting the underlying streaming connections. This
     * HTTP server needs to be attached to it in order to process any
     * connections and pase incoming streaming data as incoming HTTP request
     * messages. In its most common form, you can attach this to a
     * [`React\Socket\Server`](https://github.com/reactphp/socket#server) in
     * order to start a plaintext HTTP server like this:
     *
     * ```php
     * $server = new React\Http\Server($handler);
     *
     * $socket = new React\Socket\Server(8080, $loop);
     * $server->listen($socket);
     * ```
     *
     * See also [example #1](examples) for more details.
     *
     * This example will start listening for HTTP requests on the alternative
     * HTTP port `8080` on all interfaces (publicly). As an alternative, it is
     * very common to use a reverse proxy and let this HTTP server listen on the
     * localhost (loopback) interface only by using the listen address
     * `127.0.0.1:8080` instead. This way, you host your application(s) on the
     * default HTTP port `80` and only route specific requests to this HTTP
     * server.
     *
     * Likewise, it's usually recommended to use a reverse proxy setup to accept
     * secure HTTPS requests on default HTTPS port `443` (TLS termination) and
     * only route plaintext requests to this HTTP server. As an alternative, you
     * can also accept secure HTTPS requests with this HTTP server by attaching
     * this to a [`React\Socket\Server`](https://github.com/reactphp/socket#server)
     * using a secure TLS listen address, a certificate file and optional
     * `passphrase` like this:
     *
     * ```php
     * $server = new React\Http\Server($handler);
     *
     * $socket = new React\Socket\Server('tls://0.0.0.0:8443', $loop, array(
     *     'local_cert' => __DIR__ . '/localhost.pem'
     * ));
     * $server->listen($socket);
     * ```
     *
     * See also [example #11](examples) for more details.
     *
     * @param ServerInterface $socket
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
