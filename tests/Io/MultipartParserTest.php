<?php

namespace React\Tests\Http\Io\Middleware;

use React\Http\Io\MultipartParser;
use React\Http\Io\ServerRequest;
use React\Tests\Http\TestCase;

final class MultipartParserTest extends TestCase
{
    public function testDoesNotParseWithoutMultipartFormDataContentType()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"single\"\r\n";
        $data .= "\r\n";
        $data .= "single\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"second\"\r\n";
        $data .= "\r\n";
        $data .= "second\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data',
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertEmpty($parsedRequest->getParsedBody());
    }

    public function testPostKey()
    {
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
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertSame(
            array(
                'users' => array(
                    'one' => 'single',
                    'two' => 'second',
                ),
            ),
            $parsedRequest->getParsedBody()
        );
    }

    public function testPostStringOverwritesMap()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[one]\"\r\n";
        $data .= "\r\n";
        $data .= "ignored\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users\"\r\n";
        $data .= "\r\n";
        $data .= "2\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertSame(
            array(
                'users' => '2'
            ),
            $parsedRequest->getParsedBody()
        );
    }

    public function testPostMapOverwritesString()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users\"\r\n";
        $data .= "\r\n";
        $data .= "ignored\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[two]\"\r\n";
        $data .= "\r\n";
        $data .= "2\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertSame(
            array(
                'users' => array(
                    'two' => '2',
                ),
            ),
            $parsedRequest->getParsedBody()
        );
    }

    public function testPostVectorOverwritesString()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users\"\r\n";
        $data .= "\r\n";
        $data .= "ignored\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[]\"\r\n";
        $data .= "\r\n";
        $data .= "2\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertSame(
            array(
                'users' => array(
                    '2',
                ),
            ),
            $parsedRequest->getParsedBody()
        );
    }

    public function testPostDeeplyNestedArray()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[][]\"\r\n";
        $data .= "\r\n";
        $data .= "1\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[][]\"\r\n";
        $data .= "\r\n";
        $data .= "2\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertSame(
            array(
                'users' => array(
                    array(
                        '1'
                    ),
                    array(
                        '2'
                    )
                ),
            ),
            $parsedRequest->getParsedBody()
        );
    }

    public function testEmptyPostValue()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"key\"\r\n";
        $data .= "\r\n";
        $data .= "\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertSame(
            array(
                'key' => ''
            ),
            $parsedRequest->getParsedBody()
        );
    }

    public function testEmptyPostKey()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"\"\r\n";
        $data .= "\r\n";
        $data .= "value\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertSame(
            array(
                '' => 'value'
            ),
            $parsedRequest->getParsedBody()
        );
    }

    public function testNestedPostKeyAssoc()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"a[b][c]\"\r\n";
        $data .= "\r\n";
        $data .= "value\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertSame(
            array(
                'a' => array(
                    'b' => array(
                        'c' => 'value'
                    )
                )
            ),
            $parsedRequest->getParsedBody()
        );
    }

    public function testNestedPostKeyVector()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"a[][]\"\r\n";
        $data .= "\r\n";
        $data .= "value\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertSame(
            array(
                'a' => array(
                    array(
                        'value'
                    )
                )
            ),
            $parsedRequest->getParsedBody()
        );
    }

    public function testFileUpload()
    {
        $boundary = "---------------------------12758086162038677464950549563";

        $file = base64_decode("R0lGODlhAQABAIAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==");

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"MAX_FILE_SIZE\"\r\n";
        $data .= "\r\n";
        $data .= "12000\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[one]\"\r\n";
        $data .= "\r\n";
        $data .= "single\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[two]\"\r\n";
        $data .= "\r\n";
        $data .= "second\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-disposition: form-data; name=\"user\"\r\n";
        $data .= "\r\n";
        $data .= "single\r\n";
        $data .= "--$boundary\r\n";
        $data .= "content-Disposition: form-data; name=\"user2\"\r\n";
        $data .= "\r\n";
        $data .= "second\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[]\"\r\n";
        $data .= "\r\n";
        $data .= "first in array\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[]\"\r\n";
        $data .= "\r\n";
        $data .= "second in array\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"file\"; filename=\"Us er.php\"\r\n";
        $data .= "Content-type: text/php\r\n";
        $data .= "\r\n";
        $data .= "<?php echo 'User';\r\n";
        $data .= "\r\n";
        $data .= "--$boundary";
        $data .= "\r\n";
        $data .= "Content-Disposition: form-data; name=\"files[]\"; filename=\"blank.gif\"\r\n";
        $data .= "content-Type: image/gif\r\n";
        $data .= "X-Foo-Bar: base64\r\n";
        $data .= "\r\n";
        $data .= $file . "\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"files[]\"; filename=\"User.php\"\r\n";
        $data .= "Content-Type: text/php\r\n";
        $data .= "\r\n";
        $data .= "<?php echo 'User';\r\n\r\n";
        $data .= "\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"files[]\"; filename=\"Owner.php\"\r\n";
        $data .= "Content-Type: text/php\r\n";
        $data .= "\r\n";
        $data .= "<?php echo 'Owner';\r\n\r\n";
        $data .= "\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertSame(
            array(
                'MAX_FILE_SIZE' => '12000',
                'users' => array(
                    'one' => 'single',
                    'two' => 'second',
                    0 => 'first in array',
                    1 => 'second in array',
                ),
                'user' => 'single',
                'user2' => 'second',
            ),
            $parsedRequest->getParsedBody()
        );

        $files = $parsedRequest->getUploadedFiles();

        $this->assertTrue(isset($files['file']));
        $this->assertCount(3, $files['files']);

        $this->assertSame('Us er.php', $files['file']->getClientFilename());
        $this->assertSame('text/php', $files['file']->getClientMediaType());
        $this->assertSame("<?php echo 'User';\r\n", (string)$files['file']->getStream());

        $this->assertSame('blank.gif', $files['files'][0]->getClientFilename());
        $this->assertSame('image/gif', $files['files'][0]->getClientMediaType());
        $this->assertSame($file, (string)$files['files'][0]->getStream());

        $this->assertSame('User.php', $files['files'][1]->getClientFilename());
        $this->assertSame('text/php', $files['files'][1]->getClientMediaType());
        $this->assertSame("<?php echo 'User';\r\n\r\n", (string)$files['files'][1]->getStream());

        $this->assertSame('Owner.php', $files['files'][2]->getClientFilename());
        $this->assertSame('text/php', $files['files'][2]->getClientMediaType());
        $this->assertSame("<?php echo 'Owner';\r\n\r\n", (string)$files['files'][2]->getStream());
    }

    public function testInvalidDoubleContentDispositionUsesLast()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"ignored\"\r\n";
        $data .= "Content-Disposition: form-data; name=\"key\"\r\n";
        $data .= "\r\n";
        $data .= "value\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertSame(
            array(
                'key' => 'value'
            ),
            $parsedRequest->getParsedBody()
        );
    }

    public function testInvalidMissingNewlineAfterValueWillBeIgnored()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"key\"\r\n";
        $data .= "\r\n";
        $data .= "value";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertEmpty($parsedRequest->getParsedBody());
    }

    public function testInvalidMissingValueWillBeIgnored()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"key\"\r\n";
        $data .= "\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertEmpty($parsedRequest->getParsedBody());
    }

    public function testInvalidMissingValueAndEndBoundaryWillBeIgnored()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"key\"\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertEmpty($parsedRequest->getParsedBody());
    }

    public function testInvalidContentDispositionMissingWillBeIgnored()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Type: text/plain\r\n";
        $data .= "\r\n";
        $data .= "hello\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertEmpty($parsedRequest->getParsedBody());
    }

    public function testInvalidContentDispositionMissingValueWillBeIgnored()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition\r\n";
        $data .= "\r\n";
        $data .= "value\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertEmpty($parsedRequest->getParsedBody());
    }

    public function testInvalidContentDispositionWithoutNameWillBeIgnored()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; something=\"key\"\r\n";
        $data .= "\r\n";
        $data .= "value\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertEmpty($parsedRequest->getParsedBody());
    }

    public function testInvalidMissingEndBoundaryWillBeIgnored()
    {
        $boundary = "---------------------------5844729766471062541057622570";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"key\"\r\n";
        $data .= "\r\n";
        $data .= "value\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $this->assertEmpty($parsedRequest->getUploadedFiles());
        $this->assertSame(
            null,
            $parsedRequest->getParsedBody()
        );
    }

    public function testInvalidUploadFileWithoutContentTypeUsesNullValue()
    {
        $boundary = "---------------------------12758086162038677464950549563";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"file\"; filename=\"hello.txt\"\r\n";
        $data .= "\r\n";
        $data .= "world\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $files = $parsedRequest->getUploadedFiles();

        $this->assertCount(1, $files);
        $this->assertTrue(isset($files['file']));
        $this->assertInstanceOf('Psr\Http\Message\UploadedFileInterface', $files['file']);

        /* @var $file \Psr\Http\Message\UploadedFileInterface */
        $file = $files['file'];

        $this->assertSame('hello.txt', $file->getClientFilename());
        $this->assertNull($file->getClientMediaType());
        $this->assertSame(5, $file->getSize());
        $this->assertSame(UPLOAD_ERR_OK, $file->getError());
        $this->assertSame('world', (string)$file->getStream());
    }

    public function testInvalidUploadFileWithoutMultipleContentTypeUsesLastValue()
    {
        $boundary = "---------------------------12758086162038677464950549563";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"file\"; filename=\"hello.txt\"\r\n";
        $data .= "Content-Type: text/ignored\r\n";
        $data .= "Content-Type: text/plain\r\n";
        $data .= "\r\n";
        $data .= "world\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $files = $parsedRequest->getUploadedFiles();

        $this->assertCount(1, $files);
        $this->assertTrue(isset($files['file']));
        $this->assertInstanceOf('Psr\Http\Message\UploadedFileInterface', $files['file']);

        /* @var $file \Psr\Http\Message\UploadedFileInterface */
        $file = $files['file'];

        $this->assertSame('hello.txt', $file->getClientFilename());
        $this->assertSame('text/plain', $file->getClientMediaType());
        $this->assertSame(5, $file->getSize());
        $this->assertSame(UPLOAD_ERR_OK, $file->getError());
        $this->assertSame('world', (string)$file->getStream());
    }

    public function testUploadEmptyFile()
    {
        $boundary = "---------------------------12758086162038677464950549563";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"file\"; filename=\"empty\"\r\n";
        $data .= "Content-type: text/plain\r\n";
        $data .= "\r\n";
        $data .= "\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $files = $parsedRequest->getUploadedFiles();

        $this->assertCount(1, $files);
        $this->assertTrue(isset($files['file']));
        $this->assertInstanceOf('Psr\Http\Message\UploadedFileInterface', $files['file']);

        /* @var $file \Psr\Http\Message\UploadedFileInterface */
        $file = $files['file'];

        $this->assertSame('empty', $file->getClientFilename());
        $this->assertSame('text/plain', $file->getClientMediaType());
        $this->assertSame(0, $file->getSize());
        $this->assertSame(UPLOAD_ERR_OK, $file->getError());
        $this->assertSame('', (string)$file->getStream());
    }

    public function testUploadTooLargeFile()
    {
        $boundary = "---------------------------12758086162038677464950549563";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"file\"; filename=\"hello\"\r\n";
        $data .= "Content-type: text/plain\r\n";
        $data .= "\r\n";
        $data .= "world\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser(4);
        $parsedRequest = $parser->parse($request);

        $files = $parsedRequest->getUploadedFiles();

        $this->assertCount(1, $files);
        $this->assertTrue(isset($files['file']));
        $this->assertInstanceOf('Psr\Http\Message\UploadedFileInterface', $files['file']);

        /* @var $file \Psr\Http\Message\UploadedFileInterface */
        $file = $files['file'];

        $this->assertSame('hello', $file->getClientFilename());
        $this->assertSame('text/plain', $file->getClientMediaType());
        $this->assertSame(5, $file->getSize());
        $this->assertSame(UPLOAD_ERR_INI_SIZE, $file->getError());
    }

    public function testUploadTooLargeFileWithIniLikeSize()
    {
        $boundary = "---------------------------12758086162038677464950549563";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"file\"; filename=\"hello\"\r\n";
        $data .= "Content-type: text/plain\r\n";
        $data .= "\r\n";
        $data .= str_repeat('world', 1024) . "\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser('1K');
        $parsedRequest = $parser->parse($request);

        $files = $parsedRequest->getUploadedFiles();

        $this->assertCount(1, $files);
        $this->assertTrue(isset($files['file']));
        $this->assertInstanceOf('Psr\Http\Message\UploadedFileInterface', $files['file']);

        /* @var $file \Psr\Http\Message\UploadedFileInterface */
        $file = $files['file'];

        $this->assertSame('hello', $file->getClientFilename());
        $this->assertSame('text/plain', $file->getClientMediaType());
        $this->assertSame(5120, $file->getSize());
        $this->assertSame(UPLOAD_ERR_INI_SIZE, $file->getError());
    }

    public function testUploadNoFile()
    {
        $boundary = "---------------------------12758086162038677464950549563";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"file\"; filename=\"\"\r\n";
        $data .= "Content-type: application/octet-stream\r\n";
        $data .= "\r\n";
        $data .= "\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $files = $parsedRequest->getUploadedFiles();

        $this->assertCount(1, $files);
        $this->assertTrue(isset($files['file']));
        $this->assertInstanceOf('Psr\Http\Message\UploadedFileInterface', $files['file']);

        /* @var $file \Psr\Http\Message\UploadedFileInterface */
        $file = $files['file'];

        $this->assertSame('', $file->getClientFilename());
        $this->assertSame('application/octet-stream', $file->getClientMediaType());
        $this->assertSame(0, $file->getSize());
        $this->assertSame(UPLOAD_ERR_NO_FILE, $file->getError());
    }

    public function testUploadTooManyFilesReturnsTruncatedList()
    {
        $boundary = "---------------------------12758086162038677464950549563";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"first\"; filename=\"first\"\r\n";
        $data .= "Content-type: text/plain\r\n";
        $data .= "\r\n";
        $data .= "hello\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"second\"; filename=\"second\"\r\n";
        $data .= "Content-type: text/plain\r\n";
        $data .= "\r\n";
        $data .= "world\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser(100, 1);
        $parsedRequest = $parser->parse($request);

        $files = $parsedRequest->getUploadedFiles();

        $this->assertCount(1, $files);
        $this->assertTrue(isset($files['first']));

        $file = $files['first'];
        $this->assertSame('first', $file->getClientFilename());
        $this->assertSame('text/plain', $file->getClientMediaType());
        $this->assertSame(5, $file->getSize());
        $this->assertSame(UPLOAD_ERR_OK, $file->getError());
        $this->assertSame('hello', (string)$file->getStream());
    }

    public function testUploadTooManyFilesIgnoresEmptyFilesAndIncludesThemDespiteTruncatedList()
    {
        $boundary = "---------------------------12758086162038677464950549563";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"first\"; filename=\"first\"\r\n";
        $data .= "Content-type: text/plain\r\n";
        $data .= "\r\n";
        $data .= "hello\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"empty\"; filename=\"\"\r\n";
        $data .= "Content-type: text/plain\r\n";
        $data .= "\r\n";
        $data .= "\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"second\"; filename=\"second\"\r\n";
        $data .= "Content-type: text/plain\r\n";
        $data .= "\r\n";
        $data .= "world\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser(100, 1);
        $parsedRequest = $parser->parse($request);

        $files = $parsedRequest->getUploadedFiles();

        $this->assertCount(2, $files);
        $this->assertTrue(isset($files['first']));
        $this->assertTrue(isset($files['empty']));

        $file = $files['first'];
        $this->assertSame('first', $file->getClientFilename());
        $this->assertSame('text/plain', $file->getClientMediaType());
        $this->assertSame(5, $file->getSize());
        $this->assertSame(UPLOAD_ERR_OK, $file->getError());
        $this->assertSame('hello', (string)$file->getStream());

        $file = $files['empty'];
        $this->assertSame('', $file->getClientFilename());
        $this->assertSame('text/plain', $file->getClientMediaType());
        $this->assertSame(0, $file->getSize());
        $this->assertSame(UPLOAD_ERR_NO_FILE, $file->getError());
    }

    public function testPostMaxFileSize()
    {
        $boundary = "---------------------------12758086162038677464950549563";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"MAX_FILE_SIZE\"\r\n";
        $data .= "\r\n";
        $data .= "12\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"file\"; filename=\"User.php\"\r\n";
        $data .= "Content-type: text/php\r\n";
        $data .= "\r\n";
        $data .= "<?php echo 'User';\r\n";
        $data .= "\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $files = $parsedRequest->getUploadedFiles();

        $this->assertTrue(isset($files['file']));
        $this->assertSame(UPLOAD_ERR_FORM_SIZE, $files['file']->getError());
    }

    public function testPostMaxFileSizeIgnoredByFilesComingBeforeIt()
    {
        $boundary = "---------------------------12758086162038677464950549563";

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"file\"; filename=\"User-one.php\"\r\n";
        $data .= "Content-type: text/php\r\n";
        $data .= "\r\n";
        $data .= "<?php echo 'User';\r\n";
        $data .= "\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"MAX_FILE_SIZE\"\r\n";
        $data .= "\r\n";
        $data .= "100\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"file2\"; filename=\"User-two.php\"\r\n";
        $data .= "Content-type: text/php\r\n";
        $data .= "\r\n";
        $data .= "<?php echo 'User';\r\n";
        $data .= "\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"MAX_FILE_SIZE\"\r\n";
        $data .= "\r\n";
        $data .= "12\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"file3\"; filename=\"User-third.php\"\r\n";
        $data .= "Content-type: text/php\r\n";
        $data .= "\r\n";
        $data .= "<?php echo 'User';\r\n";
        $data .= "\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"MAX_FILE_SIZE\"\r\n";
        $data .= "\r\n";
        $data .= "0\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"file4\"; filename=\"User-forth.php\"\r\n";
        $data .= "Content-type: text/php\r\n";
        $data .= "\r\n";
        $data .= "<?php echo 'User';\r\n";
        $data .= "\r\n";
        $data .= "--$boundary--\r\n";

        $request = new ServerRequest('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ), $data, 1.1);

        $parser = new MultipartParser();
        $parsedRequest = $parser->parse($request);

        $files = $parsedRequest->getUploadedFiles();

        $this->assertTrue(isset($files['file']));
        $this->assertSame(UPLOAD_ERR_OK, $files['file']->getError());
        $this->assertTrue(isset($files['file2']));
        $this->assertSame(UPLOAD_ERR_OK, $files['file2']->getError());
        $this->assertTrue(isset($files['file3']));
        $this->assertSame(UPLOAD_ERR_FORM_SIZE, $files['file3']->getError());
        $this->assertTrue(isset($files['file4']));
        $this->assertSame(UPLOAD_ERR_OK, $files['file4']->getError());
    }
}