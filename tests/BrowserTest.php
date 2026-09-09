<?php

namespace React\Tests\Http;

use Psr\Http\Message\RequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Io\Transaction;
use React\Http\Browser;
use React\Promise\Promise;
use React\Socket\ConnectorInterface;

class BrowserTest extends TestCase
{
    private $loop;
    private $sender;
    private $browser;

    /**
     * @before
     */
    public function setUpBrowser()
    {
        $this->loop = $this->createMock(LoopInterface::class);
        $this->sender = $this->createMock(Transaction::class);
        $this->browser = new Browser(null, $this->loop);

        $ref = new \ReflectionProperty($this->browser, 'transaction');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($this->browser, $this->sender);
    }

    public function testConstructWithoutLoopAssignsLoopAutomatically()
    {
        $browser = new Browser();

        $ref = new \ReflectionProperty($browser, 'transaction');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $transaction = $ref->getValue($browser);

        $ref = new \ReflectionProperty($transaction, 'loop');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $loop = $ref->getValue($transaction);

        $this->assertInstanceOf(LoopInterface::class, $loop);
    }

    public function testConstructWithConnectorAssignsGivenConnector()
    {
        $connector = $this->createMock(ConnectorInterface::class);

        $browser = new Browser($connector);

        $ref = new \ReflectionProperty($browser, 'transaction');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $transaction = $ref->getValue($browser);

        $ref = new \ReflectionProperty($transaction, 'sender');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $sender = $ref->getValue($transaction);

        $ref = new \ReflectionProperty($sender, 'http');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $client = $ref->getValue($sender);

        $ref = new \ReflectionProperty($client, 'connectionManager');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $connectionManager = $ref->getValue($client);

        $ref = new \ReflectionProperty($connectionManager, 'connector');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ret = $ref->getValue($connectionManager);

        $this->assertSame($connector, $ret);
    }

    public function testConstructWithLoopAssignsGivenLoop()
    {
        $browser = new Browser(null, $this->loop);

        $ref = new \ReflectionProperty($browser, 'transaction');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $transaction = $ref->getValue($browser);

        $ref = new \ReflectionProperty($transaction, 'loop');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $loop = $ref->getValue($transaction);

        $this->assertSame($this->loop, $loop);
    }

    public function testGetSendsGetRequest()
    {
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals('GET', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/');
    }

    public function testPostSendsPostRequest()
    {
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals('POST', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->post('http://example.com/');
    }

    public function testHeadSendsHeadRequest()
    {
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals('HEAD', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->head('http://example.com/');
    }

    public function testPatchSendsPatchRequest()
    {
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals('PATCH', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->patch('http://example.com/');
    }

    public function testPutSendsPutRequest()
    {
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals('PUT', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->put('http://example.com/');
    }

    public function testDeleteSendsDeleteRequest()
    {
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals('DELETE', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->delete('http://example.com/');
    }

    public function testRequestOptionsSendsPutRequestWithStreamingExplicitlyDisabled()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(['streaming' => false])->willReturnSelf();

        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals('OPTIONS', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->request('OPTIONS', 'http://example.com/');
    }

    public function testRequestStreamingGetSendsGetRequestWithStreamingExplicitlyEnabled()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(['streaming' => true])->willReturnSelf();

        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals('GET', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->requestStreaming('GET', 'http://example.com/');
    }

    public function testWithTimeoutTrueSetsDefaultTimeoutOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(['timeout' => null])->willReturnSelf();

        $this->browser->withTimeout(true);
    }

    public function testWithTimeoutFalseSetsNegativeTimeoutOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(['timeout' => -1])->willReturnSelf();

        $this->browser->withTimeout(false);
    }

    public function testWithTimeout10SetsTimeoutOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(['timeout' => 10])->willReturnSelf();

        $this->browser->withTimeout(10);
    }

    public function testWithTimeoutNegativeSetsZeroTimeoutOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(['timeout' => null])->willReturnSelf();

        $this->browser->withTimeout(-10);
    }

    public function testWithFollowRedirectsTrueSetsSenderOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(['followRedirects' => true, 'maxRedirects' => null])->willReturnSelf();

        $this->browser->withFollowRedirects(true);
    }

    public function testWithFollowRedirectsFalseSetsSenderOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(['followRedirects' => false, 'maxRedirects' => null])->willReturnSelf();

        $this->browser->withFollowRedirects(false);
    }

    public function testWithFollowRedirectsTenSetsSenderOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(['followRedirects' => true, 'maxRedirects' => 10])->willReturnSelf();

        $this->browser->withFollowRedirects(10);
    }

    public function testWithFollowRedirectsZeroSetsSenderOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(['followRedirects' => true, 'maxRedirects' => 0])->willReturnSelf();

        $this->browser->withFollowRedirects(0);
    }

    public function testWithRejectErrorResponseTrueSetsSenderOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(['obeySuccessCode' => true])->willReturnSelf();

        $this->browser->withRejectErrorResponse(true);
    }

    public function testWithRejectErrorResponseFalseSetsSenderOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(['obeySuccessCode' => false])->willReturnSelf();

        $this->browser->withRejectErrorResponse(false);
    }

    public function testWithResponseBufferThousandSetsSenderOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(['maximumSize' => 1000])->willReturnSelf();

        $this->browser->withResponseBuffer(1000);
    }

    public function testWithBase()
    {
        $browser = $this->browser->withBase('http://example.com/root');

        $this->assertInstanceOf(Browser::class, $browser);
        $this->assertNotSame($this->browser, $browser);
    }

    public static function provideOtherUris()
    {
        yield 'empty returns base' => [
            'http://example.com/base',
            '',
            'http://example.com/base',
        ];
        yield 'absolute same as base returns base' => [
            'http://example.com/base',
            'http://example.com/base',
            'http://example.com/base',
        ];
        yield 'absolute below base returns absolute' => [
            'http://example.com/base',
            'http://example.com/base/another',
            'http://example.com/base/another',
        ];
        yield 'slash returns base without path' => [
            'http://example.com/base',
            '/',
            'http://example.com/',
        ];
        yield 'relative is added behind base' => [
            'http://example.com/base/',
            'test',
            'http://example.com/base/test',
        ];
        yield 'relative is added behind base without path' => [
            'http://example.com/base',
            'test',
            'http://example.com/test',
        ];
        yield 'relative level up is added behind parent path' => [
            'http://example.com/base/foo/',
            '../bar',
            'http://example.com/base/bar',
        ];
        yield 'absolute with slash is added behind base without path' => [
            'http://example.com/base',
            '/test',
            'http://example.com/test',
        ];
        yield 'query string is added behind base' => [
            'http://example.com/base',
            '?key=value',
            'http://example.com/base?key=value',
        ];
        yield 'query string is added behind base with slash' => [
            'http://example.com/base/',
            '?key=value',
            'http://example.com/base/?key=value',
        ];
        yield 'query string with slash is added behind base without path' => [
            'http://example.com/base',
            '/?key=value',
            'http://example.com/?key=value',
        ];
        yield 'absolute with query string below base is returned as-is' => [
            'http://example.com/base',
            'http://example.com/base?test',
            'http://example.com/base?test',
        ];
        yield 'urlencoded special chars will stay as-is' => [
            'http://example.com/%7Bversion%7D/',
            '',
            'http://example.com/%7Bversion%7D/'
        ];
        yield 'special chars will be urlencoded' => [
            'http://example.com/{version}/',
            '',
            'http://example.com/%7Bversion%7D/'
        ];
        yield 'other domain' => [
            'http://example.com/base/',
            'http://example.org/base/',
            'http://example.org/base/'
        ];
        yield 'other scheme' => [
            'http://example.com/base/',
            'https://example.com/base/',
            'https://example.com/base/'
        ];
        yield 'other port' => [
            'http://example.com/base/',
            'http://example.com:81/base/',
            'http://example.com:81/base/'
        ];
        yield 'other path' => [
            'http://example.com/base/',
            'http://example.com/other/',
            'http://example.com/other/'
        ];
        yield 'other path due to missing slash' => [
            'http://example.com/base/',
            'http://example.com/other',
            'http://example.com/other'
        ];
    }

    /**
     * @dataProvider provideOtherUris
     * @param string $uri
     * @param string $expected
     */
    public function testResolveUriWithBaseEndsWithoutSlash($base, $uri, $expectedAbsolute)
    {
        $browser = $this->browser->withBase($base);

        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($expectedAbsolute) {
            $this->assertEquals($expectedAbsolute, $request->getUri());
            return true;
        }))->willReturn(new Promise(function () { }));

        $browser->get($uri);
    }

    public function testWithBaseUrlNotAbsoluteFails()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->browser->withBase('hello');
    }

    public function testWithBaseUrlInvalidSchemeFails()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->browser->withBase('ftp://example.com');
    }

    public function testWithoutBaseFollowedByGetRequestTriesToSendIncompleteRequestUrl()
    {
        $this->browser = $this->browser->withBase('http://example.com')->withBase(null);

        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals('path', $request->getUri());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('path');
    }

    public function testWithProtocolVersionFollowedByGetRequestSendsRequestWithProtocolVersion()
    {
        $this->browser = $this->browser->withProtocolVersion('1.0');

        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals('1.0', $request->getProtocolVersion());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/');
    }

    public function testWithProtocolVersionInvalidThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->browser->withProtocolVersion('1.2');
    }

    public function testCancelGetRequestShouldCancelUnderlyingSocketConnection()
    {
        $pending = new Promise(function () { }, $this->expectCallableOnce());

        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())->method('connect')->with('example.com:80')->willReturn($pending);

        $this->browser = new Browser($connector, $this->loop);

        $promise = $this->browser->get('http://example.com/');
        $promise->cancel();
    }

    public function testWithHeaderShouldOverwriteExistingHeader()
    {
        $this->browser = $this->browser->withHeader('User-Agent', 'ACMC'); //should be overwritten
        $this->browser = $this->browser->withHeader('user-agent', 'ABC'); //should be the user-agent

        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals(['ABC'], $request->getHeader('UsEr-AgEnT'));
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/');
    }

    public function testWithHeaderShouldBeOverwrittenByExplicitHeaderInGetMethod()
    {
        $this->browser = $this->browser->withHeader('User-Agent', 'ACMC');

        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals(['ABC'], $request->getHeader('UsEr-AgEnT'));
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/', ['user-Agent' => 'ABC']); //should win
    }

    public function testWithMultipleHeadersShouldBeMergedCorrectlyWithMultipleDefaultHeaders()
    {
        $this->browser = $this->browser->withHeader('User-Agent', 'ACMC');
        $this->browser = $this->browser->withHeader('User-Test', 'Test');
        $this->browser = $this->browser->withHeader('Custom-HEADER', 'custom');
        $this->browser = $this->browser->withHeader('just-a-header', 'header-value');

        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $expectedHeaders = [
                'Host' => ['example.com'],

                'User-Test' => ['Test'],
                'just-a-header' => ['header-value'],

                'user-Agent' => ['ABC'],
                'another-header' => ['value'],
                'custom-header' => ['data'],
            ];

            $this->assertEquals($expectedHeaders, $request->getHeaders());
            return true;
        }))->willReturn(new Promise(function () { }));

        $headers = [
            'user-Agent' => 'ABC', //should overwrite: 'User-Agent', 'ACMC'
            'another-header' => 'value',
            'custom-header' => 'data', //should overwrite: 'Custom-header', 'custom'
        ];
        $this->browser->get('http://example.com/', $headers);
    }

    public function testWithoutHeaderShouldRemoveExistingHeader()
    {
        $this->browser = $this->browser->withHeader('User-Agent', 'ACMC');
        $this->browser = $this->browser->withoutHeader('UsEr-AgEnT'); //should remove case-insensitive header

        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals([], $request->getHeader('user-agent'));
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/');
    }

    public function testWithoutHeaderConnectionShouldRemoveDefaultConnectionHeader()
    {
        $this->browser = $this->browser->withoutHeader('Connection');

        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals([], $request->getHeader('Connection'));
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/');
    }

    public function testWithHeaderConnectionShouldOverwriteDefaultConnectionHeader()
    {
        $this->browser = $this->browser->withHeader('Connection', 'keep-alive');

        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals(['keep-alive'], $request->getHeader('Connection'));
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/');
    }

    public function testBrowserShouldSendDefaultUserAgentHeader()
    {
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals([0 => 'ReactPHP/1'], $request->getHeader('user-agent'));
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/');
    }

    public function testBrowserShouldNotSendDefaultUserAgentHeaderIfWithoutHeaderRemovesUserAgent()
    {
        $this->browser = $this->browser->withoutHeader('UsEr-AgEnT');

        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) {
            $this->assertEquals([], $request->getHeader('User-Agent'));
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/');
    }
}
