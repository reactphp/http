<?php

namespace React\Http\Psr7;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use React\Http\Io\BufferedBody;

/**
 * @internal
 */
class Utils
{

    const HEADER_REGEX = "(^([^()<>@,;:\"/[\]?={}\x01-\x20\x7F]++):[ \t]*+((?:[ \t]*+[\x21-\x7E\x80-\xFF]++)*+)[ \t]*+\r?\n)m";
    const HEADER_FOLD_REGEX = "(\r?\n[ \t]++)";

    /**
     * Creates a new PSR-7 stream.
     *
     * @param string|StreamInterface $body
     */
    public static function createStream($body = '')
    {
        if ($body instanceof StreamInterface) {
            return $body;
        }

        return new BufferedBody($body);
    }

    /**
     * Parses a response message string into a response object.
     *
     * @param string $message Response message string.
     * @return ResponseInterface
     */
    public static function parseResponse($message)
    {
        $data = self::parseMessage($message);
        // According to https://tools.ietf.org/html/rfc7230#section-3.1.2 the space
        // between status-code and reason-phrase is required. But browsers accept
        // responses without space and reason as well.
        if (!preg_match('/^HTTP\/.* \d{3}( .*|$)/', $data['start-line'])) {
            throw new \InvalidArgumentException('Invalid response string: ' . $data['start-line']);
        }
        $parts = explode(' ', $data['start-line'], 3);

        $httpPart = explode('/', $parts[0]);

        return new Response(
            (int) $parts[1],
            $data['headers'],
            $data['body'],
            $httpPart[1],
            isset($parts[2]) ? $parts[2] : null
        );
    }

    /**
     * Parses an HTTP message into an associative array.
     *
     * The array contains the "start-line" key containing the start line of
     * the message, "headers" key containing an associative array of header
     * array values, and a "body" key containing the body of the message.
     *
     * @param string $message HTTP request or response to parse.
     */
    public static function parseMessage($message)
    {
        if (!$message) {
            throw new \InvalidArgumentException('Invalid message');
        }

        $message = ltrim($message, "\r\n");

        $messageParts = preg_split("/\r?\n\r?\n/", $message, 2);

        if ($messageParts === false || count($messageParts) !== 2) {
            throw new \InvalidArgumentException('Invalid message: Missing header delimiter');
        }

        list($rawHeaders, $body) = $messageParts;
        $rawHeaders .= "\r\n"; // Put back the delimiter we split previously
        $headerParts = preg_split("/\r?\n/", $rawHeaders, 2);

        if ($headerParts === false || count($headerParts) !== 2) {
            throw new \InvalidArgumentException('Invalid message: Missing status line');
        }

        list($startLine, $rawHeaders) = $headerParts;

        if (preg_match("/(?:^HTTP\/|^[A-Z]+ \S+ HTTP\/)(\d+(?:\.\d+)?)/i", $startLine, $matches) && $matches[1] === '1.0') {
            // Header folding is deprecated for HTTP/1.1, but allowed in HTTP/1.0
            $rawHeaders = preg_replace(self::HEADER_FOLD_REGEX, ' ', $rawHeaders);
        }

        /** @var array[] $headerLines */
        $count = preg_match_all(self::HEADER_REGEX, $rawHeaders, $headerLines, PREG_SET_ORDER);

        // If these aren't the same, then one line didn't match and there's an invalid header.
        if ($count !== substr_count($rawHeaders, "\n")) {
            // Folding is deprecated, see https://tools.ietf.org/html/rfc7230#section-3.2.4
            if (preg_match(self::HEADER_FOLD_REGEX, $rawHeaders)) {
                throw new \InvalidArgumentException('Invalid header syntax: Obsolete line folding');
            }

            throw new \InvalidArgumentException('Invalid header syntax');
        }

        $headers = array();

        foreach ($headerLines as $headerLine) {
            $headers[$headerLine[1]][] = $headerLine[2];
        }

        return array(
            'start-line' => $startLine,
            'headers' => $headers,
            'body' => $body,
        );
    }
}
