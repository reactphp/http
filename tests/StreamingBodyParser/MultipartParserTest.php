<?php

namespace React\Tests\Http\StreamingBodyParser;

use Psr\Http\Message\UploadedFileInterface;
use React\Http\HttpBodyStream;
use React\Http\StreamingBodyParser\MultipartParser;
use React\Http\UploadedFile;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;
use RingCentral\Psr7\BufferStream;
use RingCentral\Psr7\Request;
use React\Promise\Stream;

class MultipartParserTest extends TestCase
{

    public function testPostKey()
    {
        $files = array();
        $post = array();

        $stream = new ThroughStream();
        $boundary = "---------------------------5844729766471062541057622570";

        $request = new Request('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/mixed; boundary=' . $boundary,
        ), new HttpBodyStream($stream, 0), 1.1);

        $parser = new MultipartParser($request);
        $parser->on('post', function ($key, $value) use (&$post) {
            $post[$key] = $value;
        });
        $parser->on('file', function ($name, UploadedFileInterface $file) use (&$files) {
            $files[] = array($name, $file);
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
            array(
                'users[one]' => 'single',
                'users[two]' => 'second',
            ),
            $post
        );
    }

    public function testFileUpload()
    {
        $files = array();
        $post = array();

        $stream = new ThroughStream();
        $boundary = "---------------------------12758086162038677464950549563";

        $request = new Request('POST', 'http://example.com/', array(
            'Content-Type' => 'multipart/form-data',
        ), new HttpBodyStream($stream, 0), 1.1);

        $multipart = new MultipartParser($request);

        $multipart->on('post', function ($key, $value) use (&$post) {
            $post[] = array($key => $value);
        });
        $multipart->on('file', function ($name, UploadedFileInterface $file, $headers) use (&$files) {
            Stream\buffer($file->getStream())->then(function ($buffer) use ($name, $file, $headers, &$files) {
                $body = new BufferStream(strlen($buffer));
                $body->write($buffer);
                $files[] = array(
                    $name,
                    new UploadedFile(
                        $body,
                        strlen($buffer),
                        $file->getError(),
                        $file->getClientFilename(),
                        $file->getClientMediaType()
                    ),
                    $headers,
                );
            }, function ($t) {
                throw $t;
            });
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
            "<?php echo 'User';\r\n\r\n");
        $stream->write("\r\n" .
            "--$boundary\r\n" .
            "Content-Disposition: form-data; name=\"files[]\"; filename=\"Owner.php\"\r\n" .
            "Content-Type: text/php\r\n" .
            "\r\n" .
            "<?php echo 'Owner';\r\n\r\n" .
            "\r\n" .
            "--$boundary--\r\n");

        $this->assertEquals(6, count($post));
        $this->assertEquals(
            array(
                array('users[one]' => 'single'),
                array('users[two]' => 'second'),
                array('user' => 'single'),
                array('user2' => 'second'),
                array('users[]' => 'first in array'),
                array('users[]' => 'second in array'),
            ),
            $post
        );

        $this->assertEquals(4, count($files));
        $this->assertEquals('file', $files[0][0]);
        $this->assertEquals('Us er.php', $files[0][1]->getClientFilename());
        $this->assertEquals('text/php', $files[0][1]->getClientMediaType());
        $this->assertEquals("<?php echo 'User';\r\n", $files[0][1]->getStream()->getContents());
        $this->assertEquals(array(
            'content-disposition' => array(
                'form-data',
                'name="file"',
                'filename="Us er.php"',
            ),
            'content-type' => array(
                'text/php',
            ),
        ), $files[0][2]);

        $this->assertEquals('files[]', $files[1][0]);
        $this->assertEquals('blank.gif', $files[1][1]->getClientFilename());
        $this->assertEquals('image/gif', $files[1][1]->getClientMediaType());
        $this->assertEquals($file, $files[1][1]->getStream()->getContents());
        $this->assertEquals(array(
            'content-disposition' => array(
                'form-data',
                'name="files[]"',
                'filename="blank.gif"',
            ),
            'content-type' => array(
                'image/gif',
            ),
            'x-foo-bar' => array(
                'base64',
            ),
        ), $files[1][2]);

        $this->assertEquals('files[]', $files[2][0]);
        $this->assertEquals('User.php', $files[2][1]->getClientFilename());
        $this->assertEquals('text/php', $files[2][1]->getClientMediaType());
        $this->assertEquals("<?php echo 'User';\r\n\r\n", $files[2][1]->getStream()->getContents());
        $this->assertEquals(array(
            'content-disposition' => array(
                'form-data',
                'name="files[]"',
                'filename="User.php"',
            ),
            'content-type' => array(
                'text/php',
            ),
        ), $files[2][2]);

        $this->assertEquals('files[]', $files[3][0]);
        $this->assertEquals('Owner.php', $files[3][1]->getClientFilename());
        $this->assertEquals('text/php', $files[3][1]->getClientMediaType());
        $this->assertEquals("<?php echo 'Owner';\r\n\r\n", $files[3][1]->getStream()->getContents());
        $this->assertEquals(array(
            'content-disposition' => array(
                'form-data',
                'name="files[]"',
                'filename="Owner.php"',
            ),
            'content-type' => array(
                'text/php',
            ),
        ), $files[3][2]);
    }
}
