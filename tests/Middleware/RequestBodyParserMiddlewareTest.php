<?php

namespace React\Tests\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\ServerRequest;
use React\Http\Middleware\RequestBodyParserMiddleware;
use React\Tests\Http\TestCase;

final class RequestBodyParserMiddlewareTest extends TestCase
{
    public function testFormUrlencodedParsing()
    {
        $middleware = new RequestBodyParserMiddleware();
        $request = new ServerRequest(
            'POST',
            'https://example.com/',
            array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'hello=world'
        );

        /** @var ServerRequestInterface $parsedRequest */
        $parsedRequest = $middleware(
            $request,
            function (ServerRequestInterface $request) {
                return $request;
            }
        );

        $this->assertSame(
            array('hello' => 'world'),
            $parsedRequest->getParsedBody()
        );
        $this->assertSame('hello=world', (string)$parsedRequest->getBody());
    }

    public function testFormUrlencodedParsingIgnoresCaseForHeadersButRespectsContentCase()
    {
        $middleware = new RequestBodyParserMiddleware();
        $request = new ServerRequest(
            'POST',
            'https://example.com/',
            array(
                'CONTENT-TYPE' => 'APPLICATION/X-WWW-Form-URLEncoded',
            ),
            'Hello=World'
        );

        /** @var ServerRequestInterface $parsedRequest */
        $parsedRequest = $middleware(
            $request,
            function (ServerRequestInterface $request) {
                return $request;
            }
        );

        $this->assertSame(
            array('Hello' => 'World'),
            $parsedRequest->getParsedBody()
        );
        $this->assertSame('Hello=World', (string)$parsedRequest->getBody());
    }

    public function testFormUrlencodedParsingNestedStructure()
    {
        $middleware = new RequestBodyParserMiddleware();
        $request = new ServerRequest(
            'POST',
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

    public function testDoesNotParseJsonByDefault()
    {
        $middleware = new RequestBodyParserMiddleware();
        $request = new ServerRequest(
            'POST',
            'https://example.com/',
            array(
                'Content-Type' => 'application/json',
            ),
            '{"hello":"world"}'
        );

        /** @var ServerRequestInterface $parsedRequest */
        $parsedRequest = $middleware(
            $request,
            function (ServerRequestInterface $request) {
                return $request;
            }
        );

        $this->assertNull($parsedRequest->getParsedBody());
        $this->assertSame('{"hello":"world"}', (string)$parsedRequest->getBody());
    }

    public function testMultipartFormDataParsing()
    {
        $middleware = new RequestBodyParserMiddleware();

        $boundary = "---------------------------12758086162038677464950549563";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[one]\"\r\n";
        $data .= "\r\n";
        $data .= "single\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[two]\"\r\n";
        $data .= "\r\n";
        $data .= "second\r\n";
        $data .= "--$boundary\r\n";


        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        /** @var ServerRequestInterface $parsedRequest */
        $parsedRequest = $middleware(
            $request,
            function (ServerRequestInterface $request) {
                return $request;
            }
        );

        $this->assertSame(
            array(
                'users' => array(
                    'one' => 'single',
                    'two' => 'second',
                ),
            ),
            $parsedRequest->getParsedBody()
        );
        $this->assertSame($data, (string)$parsedRequest->getBody());
    }
}
