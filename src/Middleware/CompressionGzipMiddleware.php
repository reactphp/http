<?php

namespace React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use function RingCentral\Psr7\stream_for;

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

    return $next($request)->then(function (Response $response) use ($request, $next) {
        if ($response->hasHeader('Content-Encoding')) {
            return $response;
        }

        $compressed = $this->compress($response->getBody()->getContents());

        return $response
            ->withHeader('Content-Encoding', 'gzip')
            ->withHeader('Content-Length', mb_strlen($compressed))
            ->withBody(stream_for($compressed));
    });
  }

  protected function compress($content)
  {
      return gzencode($content, $this->compressionLevel);
  }
}
