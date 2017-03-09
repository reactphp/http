# Changelog

## 0.6.0 (2016-03-09)

*   Feature / BC break: The `Request` and `Response` objects now follow strict
    stream semantics and their respective methods and events.
    (#116, #129, #133, #135, #136, #137, #138, #140, #141 by @legionth and
    #122, #123, #130, #131, #132, #142 by @clue)

    This implies that the `Server` now supports proper detection of the request
    message body stream, such as supporting decoding chunked transfer encoding,
    delimiting requests with an explicit `Content-Length` header
    and those with an empty request message body.

    These streaming semantics are compatible with previous Stream v0.5, future
    compatible with v0.5 and upcoming v0.6 versions and can be used like this:

    ```php
    $http->on('request', function (Request $request, Response $response) {
        $contentLength = 0;
        $request->on('data', function ($data) use (&$contentLength) {
            $contentLength += strlen($data);
        });

        $request->on('end', function () use ($response, &$contentLength){
            $response->writeHead(200, array('Content-Type' => 'text/plain'));
            $response->end("The length of the submitted request body is: " . $contentLength);
        });

        // an error occured
        // e.g. on invalid chunked encoded data or an unexpected 'end' event 
        $request->on('error', function (\Exception $exception) use ($response, &$contentLength) {
            $response->writeHead(400, array('Content-Type' => 'text/plain'));
            $response->end("An error occured while reading at length: " . $contentLength);
        });
    });
    ```

    Similarly, the `Request` and `Response` now strictly follow the
    `close()` method and `close` event semantics.
    Closing the `Request` does not interrupt the underlying TCP/IP in
    order to allow still sending back a valid response message.
    Closing the `Response` does terminate the underlying TCP/IP
    connection in order to clean up resources.

    You should make sure to always attach a `request` event listener
    like above. The `Server` will not respond to an incoming HTTP
    request otherwise and keep the TCP/IP connection pending until the
    other side chooses to close the connection.

*   Feature: Support `HTTP/1.1` and `HTTP/1.0` for `Request` and `Response`.
    (#124, #125, #126, #127, #128 by @clue and #139 by @legionth)

    The outgoing `Response` will automatically use the same HTTP version as the
    incoming `Request` message and will only apply `HTTP/1.1` semantics if
    applicable. This includes that the `Response` will automatically attach a
    `Date` and `Connection: close` header if applicable.

    This implies that the `Server` now automatically responds with HTTP error
    messages for invalid requests (status 400) and those exceeding internal
    request header limits (status 431).

## 0.5.0 (2017-02-16)

* Feature / BC break: Change `Request` methods to be in line with PSR-7
  (#117 by @clue)
  * Rename `getQuery()` to `getQueryParams()`
  * Rename `getHttpVersion()` to `getProtocolVersion()`
  * Change `getHeaders()` to always return an array of string values
    for each header

* Feature / BC break: Update Socket component to v0.5 and
  add secure HTTPS server support
  (#90 and #119 by @clue)

  ```php
  // old plaintext HTTP server
  $socket = new React\Socket\Server($loop);
  $socket->listen(8080, '127.0.0.1');
  $http = new React\Http\Server($socket);

  // new plaintext HTTP server
  $socket = new React\Socket\Server('127.0.0.1:8080', $loop);
  $http = new React\Http\Server($socket);

  // new secure HTTPS server
  $socket = new React\Socket\Server('127.0.0.1:8080', $loop);
  $socket = new React\Socket\SecureServer($socket, $loop, array(
      'local_cert' => __DIR__ . '/localhost.pem'
  ));
  $http = new React\Http\Server($socket);
  ```

* BC break: Mark internal APIs as internal or private and
  remove unneeded `ServerInterface`
  (#118 by @clue, #95 by @legionth)

## 0.4.4 (2017-02-13)

* Feature: Add request header accessors (à la PSR-7)
  (#103 by @clue)

  ```php
  // get value of host header
  $host = $request->getHeaderLine('Host');

  // get list of all cookie headers
  $cookies = $request->getHeader('Cookie');
  ```

* Feature: Forward `pause()` and `resume()` from `Request` to underlying connection
  (#110 by @clue)

  ```php
  // support back-pressure when piping request into slower destination
  $request->pipe($dest);

  // manually pause/resume request
  $request->pause();
  $request->resume();
  ```

* Fix: Fix `100-continue` to be handled case-insensitive and ignore it for HTTP/1.0.
  Similarly, outgoing response headers are now handled case-insensitive, e.g
  we no longer apply chunked transfer encoding with mixed-case `Content-Length`.
  (#107 by @clue)
  
  ```php
  // now handled case-insensitive
  $request->expectsContinue();

  // now works just like properly-cased header
  $response->writeHead($status, array('content-length' => 0));
  ```

* Fix: Do not emit empty `data` events and ignore empty writes in order to
  not mess up chunked transfer encoding
  (#108 and #112 by @clue)

* Lock and test minimum required dependency versions and support PHPUnit v5
  (#113, #115 and #114 by @andig)

## 0.4.3 (2017-02-10)

* Fix: Do not take start of body into account when checking maximum header size
  (#88 by @nopolabs)

* Fix: Remove `data` listener if `HeaderParser` emits an error
  (#83 by @nick4fake)

* First class support for PHP 5.3 through PHP 7 and HHVM
  (#101 and #102 by @clue, #66 by @WyriHaximus)

* Improve test suite by adding PHPUnit to require-dev,
  improving forward compatibility with newer PHPUnit versions
  and replacing unneeded test stubs
  (#92 and #93 by @nopolabs, #100 by @legionth)

## 0.4.2 (2016-11-09)

* Remove all listeners after emitting error in RequestHeaderParser #68 @WyriHaximus
* Catch Guzzle parse request errors #65 @WyriHaximus
* Remove branch-alias definition as per reactphp/react#343 #58 @WyriHaximus
* Add functional example to ease getting started #64 by @clue
* Naming, immutable array manipulation #37 @cboden

## 0.4.1 (2015-05-21)

* Replaced guzzle/parser with guzzlehttp/psr7 by @cboden 
* FIX Continue Header by @iannsp
* Missing type hint by @marenzo

## 0.4.0 (2014-02-02)

* BC break: Bump minimum PHP version to PHP 5.4, remove 5.3 specific hacks
* BC break: Update to React/Promise 2.0
* BC break: Update to Evenement 2.0
* Dependency: Autoloading and filesystem structure now PSR-4 instead of PSR-0
* Bump React dependencies to v0.4

## 0.3.0 (2013-04-14)

* Bump React dependencies to v0.3

## 0.2.6 (2012-12-26)

* Bug fix: Emit end event when Response closes (@beaucollins)

## 0.2.3 (2012-11-14)

* Bug fix: Forward drain events from HTTP response (@cs278)
* Dependency: Updated guzzle deps to `3.0.*`

## 0.2.2 (2012-10-28)

* Version bump

## 0.2.1 (2012-10-14)

* Feature: Support HTTP 1.1 continue

## 0.2.0 (2012-09-10)

* Bump React dependencies to v0.2

## 0.1.1 (2012-07-12)

* Version bump

## 0.1.0 (2012-07-11)

* First tagged release
