<?php

namespace React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;

final class RequestBodyParserMiddleware
{
    public function __invoke(ServerRequestInterface $request, $next)
    {
        $type = strtolower($request->getHeaderLine('Content-Type'));
        list ($type) = explode(';', $type);

        if ($type === 'application/x-www-form-urlencoded') {
            return $next($this->parseFormUrlencoded($request));
        }

        if ($type === 'multipart/form-data') {
            return $next(MultipartParser::parseRequest($request));
        }

        return $next($request);
    }

    private function parseFormUrlencoded(ServerRequestInterface $request)
    {
        $ret = array();
        parse_str((string)$request->getBody(), $ret);

        return $request->withParsedBody($ret);
    }
}
