<?php

namespace React\Http\Io\V2;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * [Internal] Abstract HTTP message base class (PSR-7)
 *
 * @internal
 * @see MessageInterface
 */
abstract class AbstractMessage implements MessageInterface
{
    /**
     * [Internal] Regex used to match all request header fields into an array, thanks to @kelunik for checking the HTTP specs and coming up with this regex
     *
     * @internal
     * @var string
     */
    const REGEX_HEADERS = '/^([^()<>@,;:\\\"\/\[\]?={}\x00-\x20\x7F]++):[\x20\x09]*+((?:[\x20\x09]*+[\x21-\x7E\x80-\xFF]++)*+)[\x20\x09]*+[\r]?+\n/m';

    /** @var array<string,string[]> */
    private $headers = array();

    /** @var array<string,string> */
    private $headerNamesLowerCase = array();

    /** @var string */
    private $protocolVersion;

    /** @var StreamInterface */
    private $body;

    /**
     * @param string $protocolVersion
     * @param array<string,string|string[]> $headers
     * @param StreamInterface $body
     */
    protected function __construct($protocolVersion, array $headers, StreamInterface $body)
    {
        foreach ($headers as $name => $value) {
            if ($value !== array()) {
                if (\is_array($value)) {
                    foreach ($value as &$one) {
                        $one = (string) $one;
                    }
                } else {
                    $value = array((string) $value);
                }

                $lower = \strtolower($name);
                if (isset($this->headerNamesLowerCase[$lower])) {
                    $value = \array_merge($this->headers[$this->headerNamesLowerCase[$lower]], $value);
                    unset($this->headers[$this->headerNamesLowerCase[$lower]]);
                }

                $this->headers[$name] = $value;
                $this->headerNamesLowerCase[$lower] = $name;
            }
        }

        $this->protocolVersion = (string) $protocolVersion;
        $this->body = $body;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): MessageInterface
    {
        if ((string) $version === $this->protocolVersion) {
            return $this;
        }

        $message = clone $this;
        $message->protocolVersion = (string) $version;

        return $message;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headerNamesLowerCase[\strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        $lower = \strtolower($name);
        return isset($this->headerNamesLowerCase[$lower]) ? $this->headers[$this->headerNamesLowerCase[$lower]] : array();
    }

    public function getHeaderLine(string $name): string
    {
        return \implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): MessageInterface
    {
        if ($value === array()) {
            return $this->withoutHeader($name);
        } elseif (\is_array($value)) {
            foreach ($value as &$one) {
                $one = (string) $one;
            }
        } else {
            $value = array((string) $value);
        }

        $lower = \strtolower($name);
        if (isset($this->headerNamesLowerCase[$lower]) && $this->headerNamesLowerCase[$lower] === (string) $name && $this->headers[$this->headerNamesLowerCase[$lower]] === $value) {
            return $this;
        }

        $message = clone $this;
        if (isset($message->headerNamesLowerCase[$lower])) {
            unset($message->headers[$message->headerNamesLowerCase[$lower]]);
        }

        $message->headers[$name] = $value;
        $message->headerNamesLowerCase[$lower] = $name;

        return $message;
    }

    public function withAddedHeader(string $name, $value): MessageInterface
    {
        if ($value === array()) {
            return $this;
        }

        return $this->withHeader($name, \array_merge($this->getHeader($name), \is_array($value) ? $value : array($value)));
    }

    public function withoutHeader(string $name): MessageInterface
    {
        $lower = \strtolower($name);
        if (!isset($this->headerNamesLowerCase[$lower])) {
            return $this;
        }

        $message = clone $this;
        unset($message->headers[$message->headerNamesLowerCase[$lower]], $message->headerNamesLowerCase[$lower]);

        return $message;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): MessageInterface
    {
        if ($body === $this->body) {
            return $this;
        }

        $message = clone $this;
        $message->body = $body;

        return $message;
    }
}
