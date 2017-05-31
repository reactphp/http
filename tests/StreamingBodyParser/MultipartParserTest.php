<?php

namespace React\Tests\Http\StreamingBodyParser;

use Psr\Http\Message\UploadedFileInterface;
use React\Http\HttpBodyStream;
use React\Http\StreamingBodyParser\MultipartParser;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;
use RingCentral\Psr7\Request;

class MultipartParserTest extends TestCase
{

    public function testPostKey()
    {
        $files = [];
        $post = [];

        $stream = new ThroughStream();
        $boundary = "---------------------------5844729766471062541057622570";

        $request = new Request('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/mixed; boundary=' . $boundary,
        ), new HttpBodyStream($stream, 0), 1.1);

        $parser = MultipartParser::create($request);
        $parser->on('post', function ($key, $value) use (&$post) {
            $post[$key] = $value;
        });
        $parser->on('file', function ($name, UploadedFileInterface $file) use (&$files) {
            $files[] = [$name, $file];
        });

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[one]\"\r\n";
        $data .= "\r\n";
        $data .= "single\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[two]\"\r\n";
        $data .= "\r\n";
        $data .= "second\r\n";
        $data .= "--$boundary--\r\n";

        $stream->write($data);

        $this->assertEmpty($files);
        $this->assertEquals(
            [
                'users[one]' => 'single',
                'users[two]' => 'second',
            ],
            $post
        );
    }

    public function testFileUpload()
    {
        $files = [];
        $post = [];

        $stream = new ThroughStream();
        $boundary = "---------------------------12758086162038677464950549563";

        $request = new Request('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data',
        ), new HttpBodyStream($stream, 0), 1.1);

        $multipart = MultipartParser::create($request);

        $multipart->on('post', function ($key, $value) use (&$post) {
            $post[] = [$key => $value];
        });
        $multipart->on('file', function ($name, /*UploadedFileInterface*/ $file, $headers) use (&$files) {
            $files[] = [$name, $file, $headers];
        });

        $file = base64_decode("R0lGODlhAQABAIAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==");

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[one]\"\r\n";
        $data .= "\r\n";
        $data .= "single\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[two]\"\r\n";
        $data .= "\r\n";
        $data .= "second\r\n";
        $stream->write($data);
        $stream->write("--$boundary\r\n");
        $stream->write("Content-disposition: form-data; name=\"user\"\r\n");
        $stream->write("\r\n");
        $stream->write("single\r\n");
        $stream->write("--$boundary\r\n");
        $stream->write("content-Disposition: form-data; name=\"user2\"\r\n");
        $stream->write("\r\n");
        $stream->write("second\r\n");
        $stream->write("--$boundary\r\n");
        $stream->write("Content-Disposition: form-data; name=\"users[]\"\r\n");
        $stream->write("\r\n");
        $stream->write("first in array\r\n");
        $stream->write("--$boundary\r\n");
        $stream->write("Content-Disposition: form-data; name=\"users[]\"\r\n");
        $stream->write("\r\n");
        $stream->write("second in array\r\n");
        $stream->write("--$boundary\r\n");
        $stream->write("Content-Disposition: form-data; name=\"file\"; filename=\"Us er.php\"\r\n");
        $stream->write("Content-type: text/php\r\n");
        $stream->write("\r\n");
        $stream->write("<?php echo 'User';\r\n");
        $stream->write("\r\n");
        $line = "--$boundary";
        $lines = str_split($line, round(strlen($line) / 2));
        $stream->write($lines[0]);
        $stream->write($lines[1]);
        $stream->write("\r\n");
        $stream->write("Content-Disposition: form-data; name=\"files[]\"; filename=\"blank.gif\"\r\n");
        $stream->write("content-Type: image/gif\r\n");
        $stream->write("X-Foo-Bar: base64\r\n");
        $stream->write("\r\n");
        $stream->write($file . "\r\n");
        $stream->write("--$boundary\r\n");
        $stream->write("Content-Disposition: form-data; name=\"files[]\"; filename=\"User.php\"\r\n" .
                       "Content-Type: text/php\r\n" .
                       "\r\n" .
                       "<?php echo 'User';\r\n");
        $stream->write("\r\n");
        $stream->write("--$boundary--\r\n");

        $this->assertEquals(6, count($post));
        $this->assertEquals(
            [
                ['users[one]' => 'single'],
                ['users[two]' => 'second'],
                ['user' => 'single'],
                ['user2' => 'second'],
                ['users[]' => 'first in array'],
                ['users[]' => 'second in array'],
            ],
            $post
        );

        $this->assertEquals(3, count($files));
        $this->assertEquals('file', $files[0][0]);
        $this->assertEquals('Us er.php', $files[0][1]->getFilename());
        $this->assertEquals('text/php', $files[0][1]->getContentType());
        $this->assertEquals([
            'content-disposition' => [
                'form-data',
                'name="file"',
                'filename="Us er.php"',
            ],
            'content-type' => [
                'text/php',
            ],
        ], $files[0][2]);

        $this->assertEquals('files[]', $files[1][0]);
        $this->assertEquals('blank.gif', $files[1][1]->getFilename());
        $this->assertEquals('image/gif', $files[1][1]->getContentType());
        $this->assertEquals([
            'content-disposition' => [
                'form-data',
                'name="files[]"',
                'filename="blank.gif"',
            ],
            'content-type' => [
                'image/gif',
            ],
            'x-foo-bar' => [
                'base64',
            ],
        ], $files[1][2]);

        $this->assertEquals('files[]', $files[2][0]);
        $this->assertEquals('User.php', $files[2][1]->getFilename());
        $this->assertEquals('text/php', $files[2][1]->getContentType());
        $this->assertEquals([
            'content-disposition' => [
                'form-data',
                'name="files[]"',
                'filename="User.php"',
            ],
            'content-type' => [
                'text/php',
            ],
        ], $files[2][2]);
    }
}
