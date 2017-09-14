<?php

namespace React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;

final class FormUrlencodedRequestBodyParserMiddleware
{
    public function __invoke(ServerRequestInterface $request, $next)
    {
        $type = $request->getHeaderLine('Content-Type');
        if ($type !== 'application/x-www-form-urlencoded') {
            return $next($request);
        }

        $ret = array();
        parse_str((string)$request->getBody(), $ret);

        $request = $request->withParsedBody($ret);

        return $next($request);
    }
}