<?php

namespace React\Tests\Http;

use Clue\React\Block;
use React\Http\Browser;
use Psr\Http\Message\RequestInterface;
use React\Promise\Promise;
use RingCentral\Psr7\Uri;

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
        $this->loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $this->sender = $this->getMockBuilder('React\Http\Io\Transaction')->disableOriginalConstructor()->getMock();
        $this->browser = new Browser($this->loop);

        $ref = new \ReflectionProperty($this->browser, 'transaction');
        $ref->setAccessible(true);
        $ref->setValue($this->browser, $this->sender);
    }

    public function testGetSendsGetRequest()
    {
        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $that->assertEquals('GET', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/');
    }

    public function testPostSendsPostRequest()
    {
        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $that->assertEquals('POST', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->post('http://example.com/');
    }

    public function testHeadSendsHeadRequest()
    {
        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $that->assertEquals('HEAD', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->head('http://example.com/');
    }

    public function testPatchSendsPatchRequest()
    {
        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $that->assertEquals('PATCH', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->patch('http://example.com/');
    }

    public function testPutSendsPutRequest()
    {
        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $that->assertEquals('PUT', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->put('http://example.com/');
    }

    public function testDeleteSendsDeleteRequest()
    {
        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $that->assertEquals('DELETE', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->delete('http://example.com/');
    }

    public function testRequestOptionsSendsPutRequestWithStreamingExplicitlyDisabled()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(array('streaming' => false))->willReturnSelf();

        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $that->assertEquals('OPTIONS', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->request('OPTIONS', 'http://example.com/');
    }

    public function testRequestStreamingGetSendsGetRequestWithStreamingExplicitlyEnabled()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(array('streaming' => true))->willReturnSelf();

        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $that->assertEquals('GET', $request->getMethod());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->requestStreaming('GET', 'http://example.com/');
    }

    public function testWithTimeoutTrueSetsDefaultTimeoutOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(array('timeout' => null))->willReturnSelf();

        $this->browser->withTimeout(true);
    }

    public function testWithTimeoutFalseSetsNegativeTimeoutOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(array('timeout' => -1))->willReturnSelf();

        $this->browser->withTimeout(false);
    }

    public function testWithTimeout10SetsTimeoutOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(array('timeout' => 10))->willReturnSelf();

        $this->browser->withTimeout(10);
    }

    public function testWithTimeoutNegativeSetsZeroTimeoutOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(array('timeout' => null))->willReturnSelf();

        $this->browser->withTimeout(-10);
    }

    public function testWithFollowRedirectsTrueSetsSenderOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(array('followRedirects' => true, 'maxRedirects' => null))->willReturnSelf();

        $this->browser->withFollowRedirects(true);
    }

    public function testWithFollowRedirectsFalseSetsSenderOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(array('followRedirects' => false, 'maxRedirects' => null))->willReturnSelf();

        $this->browser->withFollowRedirects(false);
    }

    public function testWithFollowRedirectsTenSetsSenderOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(array('followRedirects' => true, 'maxRedirects' => 10))->willReturnSelf();

        $this->browser->withFollowRedirects(10);
    }

    public function testWithFollowRedirectsZeroSetsSenderOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(array('followRedirects' => true, 'maxRedirects' => 0))->willReturnSelf();

        $this->browser->withFollowRedirects(0);
    }

    public function testWithRejectErrorResponseTrueSetsSenderOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(array('obeySuccessCode' => true))->willReturnSelf();

        $this->browser->withRejectErrorResponse(true);
    }

    public function testWithRejectErrorResponseFalseSetsSenderOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(array('obeySuccessCode' => false))->willReturnSelf();

        $this->browser->withRejectErrorResponse(false);
    }

    public function testWithResponseBufferThousandSetsSenderOption()
    {
        $this->sender->expects($this->once())->method('withOptions')->with(array('maximumSize' => 1000))->willReturnSelf();

        $this->browser->withResponseBuffer(1000);
    }

    public function testWithBase()
    {
        $browser = $this->browser->withBase('http://example.com/root');

        $this->assertInstanceOf('React\Http\Browser', $browser);
        $this->assertNotSame($this->browser, $browser);
    }

    public function provideOtherUris()
    {
        return array(
            'empty returns base' => array(
                'http://example.com/base',
                '',
                'http://example.com/base',
            ),
            'absolute same as base returns base' => array(
                'http://example.com/base',
                'http://example.com/base',
                'http://example.com/base',
            ),
            'absolute below base returns absolute' => array(
                'http://example.com/base',
                'http://example.com/base/another',
                'http://example.com/base/another',
            ),
            'slash returns added slash' => array(
                'http://example.com/base',
                '/',
                'http://example.com/base/',
            ),
            'slash does not add duplicate slash if base already ends with slash' => array(
                'http://example.com/base/',
                '/',
                'http://example.com/base/',
            ),
            'relative is added behind base' => array(
                'http://example.com/base/',
                'test',
                'http://example.com/base/test',
            ),
            'relative with slash is added behind base without duplicate slashes' => array(
                'http://example.com/base/',
                '/test',
                'http://example.com/base/test',
            ),
            'relative is added behind base with automatic slash inbetween' => array(
                'http://example.com/base',
                'test',
                'http://example.com/base/test',
            ),
            'relative with slash is added behind base' => array(
                'http://example.com/base',
                '/test',
                'http://example.com/base/test',
            ),
            'query string is added behind base' => array(
                'http://example.com/base',
                '?key=value',
                'http://example.com/base?key=value',
            ),
            'query string is added behind base with slash' => array(
                'http://example.com/base/',
                '?key=value',
                'http://example.com/base/?key=value',
            ),
            'query string with slash is added behind base' => array(
                'http://example.com/base',
                '/?key=value',
                'http://example.com/base/?key=value',
            ),
            'absolute with query string below base is returned as-is' => array(
                'http://example.com/base',
                'http://example.com/base?test',
                'http://example.com/base?test',
            ),
            'urlencoded special chars will stay as-is' => array(
                'http://example.com/%7Bversion%7D/',
                '',
                'http://example.com/%7Bversion%7D/'
            ),
            'special chars will be urlencoded' => array(
                'http://example.com/{version}/',
                '',
                'http://example.com/%7Bversion%7D/'
            ),
            'other domain' => array(
                'http://example.com/base/',
                'http://example.org/base/',
                'http://example.org/base/'
            ),
            'other scheme' => array(
                'http://example.com/base/',
                'https://example.com/base/',
                'https://example.com/base/'
            ),
            'other port' => array(
                'http://example.com/base/',
                'http://example.com:81/base/',
                'http://example.com:81/base/'
            ),
            'other path' => array(
                'http://example.com/base/',
                'http://example.com/other/',
                'http://example.com/other/'
            ),
            'other path due to missing slash' => array(
                'http://example.com/base/',
                'http://example.com/other',
                'http://example.com/other'
            ),
        );
    }

    /**
     * @dataProvider provideOtherUris
     * @param string $uri
     * @param string $expected
     */
    public function testResolveUriWithBaseEndsWithoutSlash($base, $uri, $expectedAbsolute)
    {
        $browser = $this->browser->withBase($base);

        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($expectedAbsolute, $that) {
            $that->assertEquals($expectedAbsolute, $request->getUri());
            return true;
        }))->willReturn(new Promise(function () { }));

        $browser->get($uri);
    }

    public function testWithBaseUrlNotAbsoluteFails()
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->browser->withBase('hello');
    }

    public function testWithBaseUrlInvalidSchemeFails()
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->browser->withBase('ftp://example.com');
    }

    public function testWithoutBaseFollowedByGetRequestTriesToSendIncompleteRequestUrl()
    {
        $this->browser = $this->browser->withBase('http://example.com')->withBase(null);

        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $that->assertEquals('path', $request->getUri());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('path');
    }

    public function testWithProtocolVersionFollowedByGetRequestSendsRequestWithProtocolVersion()
    {
        $this->browser = $this->browser->withProtocolVersion('1.0');

        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $that->assertEquals('1.0', $request->getProtocolVersion());
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/');
    }

    public function testWithProtocolVersionInvalidThrows()
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->browser->withProtocolVersion('1.2');
    }

    public function testCancelGetRequestShouldCancelUnderlyingSocketConnection()
    {
        $pending = new Promise(function () { }, $this->expectCallableOnce());

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('example.com:80')->willReturn($pending);

        $this->browser = new Browser($this->loop, $connector);

        $promise = $this->browser->get('http://example.com/');
        $promise->cancel();
    }
}
