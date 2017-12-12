<?php

// Simple HTML form with file upload
// Launch demo and use your favorite browser or CLI tool to test form submissions
//
// $ php examples/12-upload.php 8080
// $ curl --form name=test --form age=30 http://localhost:8080/
// $ curl --form name=hi --form avatar=@avatar.png http://localhost:8080/

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use React\EventLoop\Factory;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Middleware\RequestBodyParserMiddleware;
use React\Http\Response;
use React\Http\StreamingServer;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$handler = function (ServerRequestInterface $request) {
    if ($request->getMethod() === 'POST') {
        // Take form input values from POST values (for illustration purposes only!)
        // Does not actually validate data here
        $body = $request->getParsedBody();
        $name = isset($body['name']) && is_string($body['name']) ? htmlspecialchars($body['name']) : 'n/a';
        $age = isset($body['age']) && is_string($body['age']) ? (int)$body['age'] : 'n/a';

        // Show uploaded avatar as image (for illustration purposes only!)
        // Real applications should validate the file data to ensure this is
        // actually an image and not rely on the client media type.
        $avatar = 'n/a';
        $uploads = $request->getUploadedFiles();
        if (isset($uploads['avatar']) && $uploads['avatar'] instanceof UploadedFileInterface) {
            /* @var $file UploadedFileInterface */
            $file = $uploads['avatar'];
            if ($file->getError() === UPLOAD_ERR_OK) {
                // Note that moveFile() is not available due to its blocking nature.
                // You can use your favorite data store to simply dump the file
                // contents via `(string)$file->getStream()` instead.
                // Here, we simply use an inline image to send back to client:
                $avatar = '<img src="data:'. $file->getClientMediaType() . ';base64,' . base64_encode($file->getStream()) . '" /> (' . $file->getSize() . ' bytes)';
            } elseif ($file->getError() === UPLOAD_ERR_INI_SIZE) {
                $avatar = 'upload exceeds file size limit';
            } else {
                // Real applications should probably check the error number and
                // should print some human-friendly text
                $avatar = 'upload error ' . $file->getError();
            }
        }

        $dump = htmlspecialchars(
            var_export($request->getParsedBody(), true) .
            PHP_EOL .
            var_export($request->getUploadedFiles(), true)
        );

        $body = <<<BODY
Name: $name
Age: $age
Avatar $avatar

<pre>
$dump
</pre>
BODY;
    } else {
        $body = <<<BODY
<form method="POST" enctype="multipart/form-data">
    <label>
      Your name
      <input type="text" name="name" required />
    </label>

    <label>
      Your age
      <input type="number" name="age" min="9" max="99" required />
    </label>

    <label>
      Upload avatar (optional, 100KB max)
      <input type="file" name="avatar" />
    </label>

    <button type="submit">
      » Submit
    </button>
  </form>
BODY;
    }

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<style>
body{
    background-color: #eee;
    color: #aaa;
}
label{
    display: block;
    margin-bottom: .5em;
}
</style>
<body>
$body
</body>
</html>

HTML;

    return new Response(
        200,
        array(
            'Content-Type' => 'text/html; charset=UTF-8'
        ),
        $html
    );
};

// Note how this example explicitly uses the advanced `StreamingServer` to apply
// custom request buffering limits below before running our request handler.
$server = new StreamingServer(array(
    new LimitConcurrentRequestsMiddleware(100), // 100 concurrent buffering handlers, queue otherwise
    new RequestBodyBufferMiddleware(8 * 1024 * 1024), // 8 MiB max, ignore body otherwise
    new RequestBodyParserMiddleware(100 * 1024, 1), // 1 file with 100 KiB max, reject upload otherwise
    $handler
));

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
