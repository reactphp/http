<?php

namespace React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;

final class RequestBodyParserMiddleware
{
    private $types = array();

    public function __construct()
    {
        $this->addType('application/x-www-form-urlencoded', function (ServerRequestInterface $request) {
            $ret = array();
            parse_str((string)$request->getBody(), $ret);

            return $request->withParsedBody($ret);
        });
    }

    public function addType($type, $callback)
    {
        $this->types[$type] = $callback;
    }

    public function __invoke(ServerRequestInterface $request, $next)
    {
        $type = $request->getHeaderLine('Content-Type');

        if (!isset($this->types[$type])) {
            return $next($request);
        }

        try {
            $parser = $this->types[$type];
            /** @var ServerRequestInterface $request */
            $request = $parser($request);
        } catch (\Exception $e) {
            return $next($request);
        } catch (\Throwable $t) {
            return $next($request);
        }

        return $next($request);
    }
}
