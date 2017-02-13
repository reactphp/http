# Changelog

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
