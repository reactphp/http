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

    public function testFormUrlencodedIgnoresBodyWithExcessiveNesting()
    {
        // supported in all Zend PHP versions and HHVM
        // ini setting does exist everywhere but HHVM: https://3v4l.org/hXLiK
        // HHVM limits to 64 and returns an empty array structure: https://3v4l.org/j3DK2
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on HHVM (limited to depth 64, but keeps empty array structure)');
        }

        $allowed = (int)ini_get('max_input_nesting_level');

        $middleware = new RequestBodyParserMiddleware();
        $request = new ServerRequest(
            'POST',
            'https://example.com/',
            array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'hello' . str_repeat('[]', $allowed + 1) . '=world'
        );

        /** @var ServerRequestInterface $parsedRequest */
        $parsedRequest = $middleware(
            $request,
            function (ServerRequestInterface $request) {
                return $request;
            }
        );

        $this->assertSame(
            array(),
            $parsedRequest->getParsedBody()
        );
    }

    public function testFormUrlencodedTruncatesBodyWithExcessiveLength()
    {
        // supported as of PHP 5.3.11, no HHVM support: https://3v4l.org/PiqnQ
        // ini setting already exists in PHP 5.3.9: https://3v4l.org/VF6oV
        if (defined('HHVM_VERSION') || PHP_VERSION_ID < 50311) {
            $this->markTestSkipped('Not supported on HHVM and PHP < 5.3.11 (unlimited length)');
        }

        $allowed = (int)ini_get('max_input_vars');

        $middleware = new RequestBodyParserMiddleware();
        $request = new ServerRequest(
            'POST',
            'https://example.com/',
            array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            str_repeat('a[]=b&', $allowed + 1)
        );

        /** @var ServerRequestInterface $parsedRequest */
        $parsedRequest = $middleware(
            $request,
            function (ServerRequestInterface $request) {
                return $request;
            }
        );

        $body = $parsedRequest->getParsedBody();

        $this->assertCount(1, $body);
        $this->assertTrue(isset($body['a']));
        $this->assertCount($allowed, $body['a']);
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
        $data .= "--$boundary--\r\n";

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

    public function testMultipartFormDataIgnoresFieldWithExcessiveNesting()
    {
        // supported in all Zend PHP versions and HHVM
        // ini setting does exist everywhere but HHVM: https://3v4l.org/hXLiK
        // HHVM limits to 64 and otherwise returns an empty array structure
        $allowed = (int)ini_get('max_input_nesting_level');
        if ($allowed === 0) {
            $allowed = 64;
        }

        $middleware = new RequestBodyParserMiddleware();

        $boundary = "---------------------------12758086162038677464950549563";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"hello" . str_repeat("[]", $allowed + 1) . "\"\r\n";
        $data .= "\r\n";
        $data .= "world\r\n";
        $data .= "--$boundary--\r\n";

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

        $this->assertEmpty($parsedRequest->getParsedBody());
    }

    public function testMultipartFormDataTruncatesBodyWithExcessiveLength()
    {
        // ini setting exists in PHP 5.3.9, not in HHVM: https://3v4l.org/VF6oV
        // otherwise default to 1000 as implemented within
        $allowed = (int)ini_get('max_input_vars');
        if ($allowed === 0) {
            $allowed = 1000;
        }

        $middleware = new RequestBodyParserMiddleware();

        $boundary = "---------------------------12758086162038677464950549563";

        $data  = "";
        for ($i = 0; $i < $allowed + 1; ++$i) {
            $data .= "--$boundary\r\n";
            $data .= "Content-Disposition: form-data; name=\"a[]\"\r\n";
            $data .= "\r\n";
            $data .= "b\r\n";
        }
        $data .= "--$boundary--\r\n";

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

        $body = $parsedRequest->getParsedBody();

        $this->assertCount(1, $body);
        $this->assertTrue(isset($body['a']));
        $this->assertCount($allowed, $body['a']);
    }

    public function testMultipartFormDataTruncatesExcessiveNumberOfEmptyFileUploads()
    {
        // ini setting exists in PHP 5.3.9, not in HHVM: https://3v4l.org/VF6oV
        // otherwise default to 1000 as implemented within
        $allowed = (int)ini_get('max_input_vars');
        if ($allowed === 0) {
            $allowed = 1000;
        }

        $middleware = new RequestBodyParserMiddleware();

        $boundary = "---------------------------12758086162038677464950549563";

        $data  = "";
        for ($i = 0; $i < $allowed + 1; ++$i) {
            $data .= "--$boundary\r\n";
            $data .= "Content-Disposition: form-data; name=\"empty[]\"; filename=\"\"\r\n";
            $data .= "\r\n";
            $data .= "\r\n";
        }
        $data .= "--$boundary--\r\n";

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

        $body = $parsedRequest->getUploadedFiles();
        $this->assertCount(1, $body);
        $this->assertTrue(isset($body['empty']));
        $this->assertCount($allowed, $body['empty']);
    }
}
