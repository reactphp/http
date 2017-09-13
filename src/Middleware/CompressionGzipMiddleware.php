<?php

namespace React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;

final class CompressionGzipMiddleware
{

    private $compressionLevel = -1;

    public function __construct($level = -1)
    {
        $this->compressionLevel = $level;
    }

    public function __invoke(ServerRequestInterface $request, $next)
    {
        if (!$request->hasHeader('Accept-Encoding')) {
            return $next($request);
        }

        if (stristr($request->getHeaderLine('Accept-Encoding'), 'gzip') === false) {
            return $next($request);
        }

        $level = $this->compressionLevel;
        return $next($request)->then(function (Response $response) use ($request, $next, $level) {
          if ($response->hasHeader('Content-Encoding')) {
              return $response;
          }

          $content = $response->getBody()->getContents();
          $content = gzencode($content, $level, FORCE_GZIP);

          return $response
              ->withHeader('Content-Encoding', 'gzip')
              ->withHeader('Content-Length', mb_strlen($content))
              ->withBody(\RingCentral\Psr7\stream_for($content));
        });
    }
}
