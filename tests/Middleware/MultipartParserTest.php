<?php

namespace React\Tests\Http\Middleware;

use React\Http\Middleware\MultipartParser;
use React\Http\ServerRequest;
use React\Tests\Http\TestCase;

final class MultipartParserTest extends TestCase
{
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
            'Content-Type' => 'multipart/mixed; boundary=' . $boundary,
        ), $data, 1.1);

        $parsedRequest = MultipartParser::parseRequest($request);

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

    public function testFileUpload()
    {
        $boundary = "---------------------------12758086162038677464950549563";

        $file = base64_decode("R0lGODlhAQABAIAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==");

        $data  = "--$boundary\r\n";
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
            'Content-Type' => 'multipart/form-data',
        ), $data, 1.1);

        $parsedRequest = MultipartParser::parseRequest($request);

        $this->assertSame(
            array(
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
}