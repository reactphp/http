<?php

namespace React\Tests\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Middleware\MultipartRequestBodyParserMiddleware;
use React\Http\ServerRequest;
use React\Tests\Http\TestCase;

final class MultipartRequestBodyParserMiddlewareTest extends TestCase
{
    public function testMultipartMixedParsing()
    {
        $middleware = new MultipartRequestBodyParserMiddleware();

        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[one]\"\r\n";
        $data .= "\r\n";
        $data .= "single\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[two]\"\r\n";
        $data .= "\r\n";
        $data .= "second\r\n";
        $data .= "--$boundary--\r\n";


        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/mixed; boundary=' . $boundary,
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

    public function testMultipartFormDataParsing()
    {
        $middleware = new MultipartRequestBodyParserMiddleware();

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
            'Content-Type' => 'multipart/form-data',
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