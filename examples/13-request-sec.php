<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Middleware\Callback;
use React\Http\MiddlewareInterface;
use React\Http\MiddlewareStackInterface;
use React\Http\Response;
use React\Http\Server;
use React\Promise\Deferred;

require __DIR__ . '/../vendor/autoload.php';

$total = 0;
$counts = array(
    'requests'  => 0,
    'responses' => 0,
);
final class Incre implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, MiddlewareStackInterface $stack)
    {
        global $counts, $total;
        $total++;
        $counts['requests']++;
        return $stack->process($request)->then(function ($response) {
            global $counts;
            $counts['responses']++;
            return $response;
        });
    }
}

$loop = Factory::create();

$loop->addPeriodicTimer(1, function () use (&$counts, &$total) {
    echo 'Req/s:  ', number_format($counts['requests']), PHP_EOL;
    echo 'Resp/s: ', number_format($counts['responses']), PHP_EOL;
    echo 'Total:  ', number_format($total), PHP_EOL;
    echo '---------------------', PHP_EOL;
    $counts = array(
        'requests'  => 0,
        'responses' => 0,
    );
});
$server = new Server(array(
    new Incre($counts),
    new Callback(function (ServerRequestInterface $request) use ($loop) {
        $deferred = new Deferred();
        $loop->addTimer(mt_rand(1, 10) / 10, function () use ($deferred) {
            $deferred->resolve(new Response(
                200,
                array(
                    'Content-Type' => 'text/plain'
                ),
                "Hello world\n"
            ));
        });
        return $deferred->promise();
    })
));

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();
