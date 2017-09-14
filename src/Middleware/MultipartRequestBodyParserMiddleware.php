<?php

namespace React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;

final class MultipartRequestBodyParserMiddleware
{
    public function __invoke(ServerRequestInterface $request, $next)
    {
        $type = $request->getHeaderLine('Content-Type');
        list ($type) = explode(';', $type);

        if ($type !== 'multipart/form-data' && $type !== 'multipart/mixed') {
            return $next($request);
        }

        $request = MultipartParser::parseRequest($request);

        return $next($request);
    }
}