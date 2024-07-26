<?php

namespace React\Tests\Http\Io;

use Psr\Http\Message\StreamInterface;
use React\Http\Io\AbstractMessage;
use React\Tests\Http\TestCase;

class MessageMock extends AbstractMessage
{
    /**
     * @param string $protocolVersion
     * @param array<string,string|string[]> $headers
     * @param StreamInterface $body
     */
    public function __construct($protocolVersion, array $headers, StreamInterface $body)
    {
        parent::__construct($protocolVersion, $headers, $body);
    }
}

class AbstractMessageTest extends TestCase
{
    public function testWithProtocolVersionReturnsNewInstanceWhenProtocolVersionIsChanged()
    {
        $message = new MessageMock(
            '1.1',
            [],
            $this->createMock(StreamInterface::class)
        );

        $new = $message->withProtocolVersion('1.0');
        $this->assertNotSame($message, $new);
        $this->assertEquals('1.0', $new->getProtocolVersion());
        $this->assertEquals('1.1', $message->getProtocolVersion());
    }

    public function testWithProtocolVersionReturnsSameInstanceWhenProtocolVersionIsUnchanged()
    {
        $message = new MessageMock(
            '1.1',
            [],
            $this->createMock(StreamInterface::class)
        );

        $new = $message->withProtocolVersion('1.1');
        $this->assertSame($message, $new);
        $this->assertEquals('1.1', $message->getProtocolVersion());
    }

    public function testHeaderWithStringValue()
    {
        $message = new MessageMock(
            '1.1',
            [
                'Content-Type' => 'text/plain'
            ],
            $this->createMock(StreamInterface::class)
        );

        $this->assertEquals(['Content-Type' => ['text/plain']], $message->getHeaders());

        $this->assertEquals(['text/plain'], $message->getHeader('Content-Type'));
        $this->assertEquals(['text/plain'], $message->getHeader('CONTENT-type'));

        $this->assertEquals('text/plain', $message->getHeaderLine('Content-Type'));
        $this->assertEquals('text/plain', $message->getHeaderLine('CONTENT-Type'));

        $this->assertTrue($message->hasHeader('Content-Type'));
        $this->assertTrue($message->hasHeader('content-TYPE'));

        $new = $message->withHeader('Content-Type', 'text/plain');
        $this->assertSame($message, $new);

        $new = $message->withHeader('Content-Type', ['text/plain']);
        $this->assertSame($message, $new);

        $new = $message->withHeader('content-type', 'text/plain');
        $this->assertNotSame($message, $new);
        $this->assertEquals(['content-type' => ['text/plain']], $new->getHeaders());
        $this->assertEquals(['Content-Type' => ['text/plain']], $message->getHeaders());

        $new = $message->withHeader('Content-Type', 'text/html');
        $this->assertNotSame($message, $new);
        $this->assertEquals(['Content-Type' => ['text/html']], $new->getHeaders());
        $this->assertEquals(['Content-Type' => ['text/plain']], $message->getHeaders());

        $new = $message->withHeader('Content-Type', ['text/html']);
        $this->assertNotSame($message, $new);
        $this->assertEquals(['Content-Type' => ['text/html']], $new->getHeaders());
        $this->assertEquals(['Content-Type' => ['text/plain']], $message->getHeaders());

        $new = $message->withAddedHeader('Content-Type', []);
        $this->assertSame($message, $new);

        $new = $message->withoutHeader('Content-Type');
        $this->assertNotSame($message, $new);
        $this->assertEquals([], $new->getHeaders());
        $this->assertEquals(['Content-Type' => ['text/plain']], $message->getHeaders());
    }

    public function testHeaderWithMultipleValues()
    {
        $message = new MessageMock(
            '1.1',
            [
                'Set-Cookie' => [
                    'a=1',
                    'b=2'
                ]
            ],
            $this->createMock(StreamInterface::class)
        );

        $this->assertEquals(['Set-Cookie' => ['a=1', 'b=2']], $message->getHeaders());

        $this->assertEquals(['a=1', 'b=2'], $message->getHeader('Set-Cookie'));
        $this->assertEquals(['a=1', 'b=2'], $message->getHeader('Set-Cookie'));

        $this->assertEquals('a=1, b=2', $message->getHeaderLine('Set-Cookie'));
        $this->assertEquals('a=1, b=2', $message->getHeaderLine('Set-Cookie'));

        $this->assertTrue($message->hasHeader('Set-Cookie'));
        $this->assertTrue($message->hasHeader('Set-Cookie'));

        $new = $message->withHeader('Set-Cookie', ['a=1', 'b=2']);
        $this->assertSame($message, $new);

        $new = $message->withHeader('Set-Cookie', ['a=1', 'b=2', 'c=3']);
        $this->assertNotSame($message, $new);
        $this->assertEquals(['Set-Cookie' => ['a=1', 'b=2', 'c=3']], $new->getHeaders());
        $this->assertEquals(['Set-Cookie' => ['a=1', 'b=2']], $message->getHeaders());

        $new = $message->withAddedHeader('Set-Cookie', []);
        $this->assertSame($message, $new);

        $new = $message->withAddedHeader('Set-Cookie', 'c=3');
        $this->assertNotSame($message, $new);
        $this->assertEquals(['Set-Cookie' => ['a=1', 'b=2', 'c=3']], $new->getHeaders());
        $this->assertEquals(['Set-Cookie' => ['a=1', 'b=2']], $message->getHeaders());

        $new = $message->withAddedHeader('Set-Cookie', ['c=3']);
        $this->assertNotSame($message, $new);
        $this->assertEquals(['Set-Cookie' => ['a=1', 'b=2', 'c=3']], $new->getHeaders());
        $this->assertEquals(['Set-Cookie' => ['a=1', 'b=2']], $message->getHeaders());
    }

    public function testHeaderWithEmptyValue()
    {
        $message = new MessageMock(
            '1.1',
            [
                'Content-Type' => []
            ],
            $this->createMock(StreamInterface::class)
        );

        $this->assertEquals([], $message->getHeaders());

        $this->assertEquals([], $message->getHeader('Content-Type'));
        $this->assertEquals('', $message->getHeaderLine('Content-Type'));
        $this->assertFalse($message->hasHeader('Content-Type'));

        $new = $message->withHeader('Empty', []);
        $this->assertSame($message, $new);
        $this->assertFalse($new->hasHeader('Empty'));

        $new = $message->withAddedHeader('Empty', []);
        $this->assertSame($message, $new);
        $this->assertFalse($new->hasHeader('Empty'));

        $new = $message->withoutHeader('Empty');
        $this->assertSame($message, $new);
        $this->assertFalse($new->hasHeader('Empty'));
    }

    public function testHeaderWithMultipleValuesAcrossMixedCaseNamesInConstructorMergesAllValuesWithNameFromLastNonEmptyValue()
    {
        $message = new MessageMock(
            '1.1',
            [
                'SET-Cookie' => 'a=1',
                'set-cookie' => ['b=2'],
                'set-COOKIE' => []
            ],
            $this->createMock(StreamInterface::class)
        );

        $this->assertEquals(['set-cookie' => ['a=1', 'b=2']], $message->getHeaders());
        $this->assertEquals(['a=1', 'b=2'], $message->getHeader('Set-Cookie'));
    }

    public function testWithBodyReturnsNewInstanceWhenBodyIsChanged()
    {
        $body = $this->createMock(StreamInterface::class);
        $message = new MessageMock(
            '1.1',
            [],
            $body
        );

        $body2 = $this->createMock(StreamInterface::class);
        $new = $message->withBody($body2);
        $this->assertNotSame($message, $new);
        $this->assertSame($body2, $new->getBody());
        $this->assertSame($body, $message->getBody());
    }

    public function testWithBodyReturnsSameInstanceWhenBodyIsUnchanged()
    {
        $body = $this->createMock(StreamInterface::class);
        $message = new MessageMock(
            '1.1',
            [],
            $body
        );

        $new = $message->withBody($body);
        $this->assertSame($message, $new);
        $this->assertEquals($body, $message->getBody());
    }
}
