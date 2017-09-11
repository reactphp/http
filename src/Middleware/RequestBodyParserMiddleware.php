<?php

namespace React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;

final class RequestBodyParserMiddleware
{
    private $keepOriginalBody;
    private $types = array();

    /**
     * @param bool $keepOriginalBody Keep the original body after parsing or not
     */
    public function __construct($keepOriginalBody = false)
    {
        $this->keepOriginalBody = $keepOriginalBody;
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
            $value = $this->types[$type];
            /** @var ServerRequestInterface $request */
            $request = $value($request);
        } catch (\Exception $e) {
            return $next($request);
        }

        if (!$this->keepOriginalBody) {
            $request = $request->withBody(Psr7\stream_for());
        }

        return $next($request);
    }
}
