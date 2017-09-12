<?php

namespace React\Tests\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Middleware\CompressionGzipMiddleware;
use React\Http\Response;
use React\Http\ServerRequest;
use React\Promise\FulfilledPromise;
use React\Promise\Promise;
use React\Tests\Http\TestCase;

class CompressionGzipMiddlewareTest extends TestCase
{

    public function testNoAutoCompressWhenNoSpecificHeadersArePresent()
    {
        $content = 'Some response';

        $request = new ServerRequest('GET', 'https://example.com/');
        $response = new Response(200, array(), $content);
        $next = $this->getNextCallback($request, $response);
        $middleware = new CompressionGzipMiddleware();

        /** @var FulfilledPromise $result */
        $result = $middleware($request, $next);
        $result->done(function ($value) use (&$response) {
            $response = $value;
        });

        $this->assertNotNull($response);
        $this->assertInstanceOf('React\Http\Response', $response);
        $this->assertFalse($response->hasHeader('Content-Encoding'));
        $this->assertSame($content, $response->getBody()->getContents());
    }

    public function testCompressWhenGzipHeadersArePresent()
    {
        $content = 'Some response';

        $request = new ServerRequest('GET', 'https://example.com/', array('Accept-Encoding' => 'gzip, deflate, br'));
        $response = new Response(200, array(), $content);
        $next = $this->getNextCallback($request, $response);
        $middleware = new CompressionGzipMiddleware();

        /** @var FulfilledPromise $result */
        $result = $middleware($request, $next);
        $result->done(function ($value) use (&$response) {
            $response = $value;
        });

        $this->assertNotNull($response);
        $this->assertInstanceOf('React\Http\Response', $response);
        $this->assertTrue($response->hasHeader('Content-Encoding'));
        $this->assertTrue($response->hasHeader('Content-Length'));
        $this->assertSame('gzip', $response->getHeaderLine('Content-Encoding'));
        $this->assertSame($content, gzdecode($response->getBody()->getContents(), $response->getHeaderLine('Content-Length')));
    }

    public function testMiddlewareSkipWhenGzipIsNotSupportedByClient()
    {
        $content = 'Some response';

        $request = new ServerRequest('GET', 'https://example.com/', array('Accept-Encoding' => 'deflate, br'));
        $response = new Response(200, array(), $content);
        $next = $this->getNextCallback($request, $response);
        $middleware = new CompressionGzipMiddleware();

        /** @var FulfilledPromise $result */
        $result = $middleware($request, $next);
        $result->done(function ($value) use (&$response) {
            $response = $value;
        });

        $this->assertNotNull($response);
        $this->assertInstanceOf('React\Http\Response', $response);
        $this->assertFalse($response->hasHeader('Content-Encoding'));
        $this->assertSame($content, $response->getBody()->getContents());
    }

    public function testShouldSkipMiddlewareWhenResponseIsAlreadyCompressed()
    {
        $content = 'Some response';

        $request = new ServerRequest('GET', 'https://example.com/', array('Accept-Encoding' => 'deflate, br'));
        $response = new Response(200, array('Content-Encoding' => 'br'), $content);
        $next = $this->getNextCallback($request, $response);
        $middleware = new CompressionGzipMiddleware();

        /** @var FulfilledPromise $result */
        $result = $middleware($request, $next);
        $result->done(function ($value) use (&$response) {
            $response = $value;
        });

        $this->assertNotNull($response);
        $this->assertInstanceOf('React\Http\Response', $response);
        $this->assertTrue($response->hasHeader('Content-Encoding'));
    }

    public function getNextCallback(ServerRequest $req, Response $response)
    {
        return function (ServerRequestInterface $request) use (&$response) {
            return new Promise(function ($resolve, $reject) use ($request, &$response) {
                return $resolve($response);
            });
        };
    }

}
