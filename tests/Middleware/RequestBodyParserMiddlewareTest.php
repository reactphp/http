<?php

namespace React\Tests\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Middleware\RequestBodyParserMiddleware;
use React\Http\ServerRequest;
use React\Tests\Http\TestCase;
use RingCentral\Psr7\BufferStream;
use React\Stream\ThroughStream;
use React\Http\HttpBodyStream;

final class RequestBodyParserMiddlewareTest extends TestCase
{
    public function testParse()
    {
        $middleware = new RequestBodyParserMiddleware();
        $middleware->addType('react/http', function (ServerRequestInterface $request) {
            return $request->withParsedBody('parsed');
        });

        $request = new ServerRequest(
            200,
            'https://example.com/',
            array(
                'Content-Type' => 'react/http',
            ),
            'not yet parsed'
        );

        /** @var ServerRequestInterface $parsedRequest */
        $parsedRequest = $middleware(
            $request,
            function (ServerRequestInterface $request) {
                return $request;
            }
        );

        $this->assertSame('parsed', $parsedRequest->getParsedBody());
        $this->assertSame('not yet parsed', (string)$parsedRequest->getBody());
    }
    public function testFormUrlencodedParsing()
    {
        $middleware = new RequestBodyParserMiddleware();
        $request = new ServerRequest(
            200,
            'https://example.com/',
            array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'foo=bar&baz[]=cheese&bar[]=beer&bar[]=wine&market[fish]=salmon&market[meat][]=beef&market[meat][]=chicken&market[]=bazaar'
        );

        /** @var ServerRequestInterface $parsedRequest */
        $parsedRequest = $middleware(
            $request,
            function (ServerRequestInterface $request) {
                return $request;
            }
        );

        $this->assertSame(
            array(
                'foo' => 'bar',
                'baz' => array(
                    'cheese',
                ),
                'bar' => array(
                    'beer',
                    'wine',
                ),
                'market' => array(
                    'fish' => 'salmon',
                    'meat' => array(
                        'beef',
                        'chicken',
                    ),
                    0 => 'bazaar',
                ),
            ),
            $parsedRequest->getParsedBody()
        );
        $this->assertSame('foo=bar&baz[]=cheese&bar[]=beer&bar[]=wine&market[fish]=salmon&market[meat][]=beef&market[meat][]=chicken&market[]=bazaar', (string)$parsedRequest->getBody());
    }
}
