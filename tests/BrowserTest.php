<?php

namespace React\Tests\Http;

use Psr\Http\Message\RequestInterface;
use React\Http\Browser;
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
        $this->browser = new Browser(null, $this->loop);

        $ref = new \ReflectionProperty($this->browser, 'transaction');
        $ref->setAccessible(true);
        $ref->setValue($this->browser, $this->sender);
    }

    public function testConstructWithoutLoopAssignsLoopAutomatically()
    {
        $browser = new Browser();

        $ref = new \ReflectionProperty($browser, 'transaction');
        $ref->setAccessible(true);
        $transaction = $ref->getValue($browser);

        $ref = new \ReflectionProperty($transaction, 'loop');
        $ref->setAccessible(true);
        $loop = $ref->getValue($transaction);

        $this->assertInstanceOf('React\EventLoop\LoopInterface', $loop);
    }

    public function testConstructWithConnectorAssignsGivenConnector()
    {
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();

        $browser = new Browser($connector);

        $ref = new \ReflectionProperty($browser, 'transaction');
        $ref->setAccessible(true);
        $transaction = $ref->getValue($browser);

        $ref = new \ReflectionProperty($transaction, 'sender');
        $ref->setAccessible(true);
        $sender = $ref->getValue($transaction);

        $ref = new \ReflectionProperty($sender, 'http');
        $ref->setAccessible(true);
        $client = $ref->getValue($sender);

        $ref = new \ReflectionProperty($client, 'connector');
        $ref->setAccessible(true);
        $ret = $ref->getValue($client);

        $this->assertSame($connector, $ret);
    }

    public function testConstructWithConnectorWithLegacySignatureAssignsGivenConnector()
    {
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();

        $browser = new Browser(null, $connector);

        $ref = new \ReflectionProperty($browser, 'transaction');
        $ref->setAccessible(true);
        $transaction = $ref->getValue($browser);

        $ref = new \ReflectionProperty($transaction, 'sender');
        $ref->setAccessible(true);
        $sender = $ref->getValue($transaction);

        $ref = new \ReflectionProperty($sender, 'http');
        $ref->setAccessible(true);
        $client = $ref->getValue($sender);

        $ref = new \ReflectionProperty($client, 'connector');
        $ref->setAccessible(true);
        $ret = $ref->getValue($client);

        $this->assertSame($connector, $ret);
    }

    public function testConstructWithLoopAssignsGivenLoop()
    {
        $browser = new Browser(null, $this->loop);

        $ref = new \ReflectionProperty($browser, 'transaction');
        $ref->setAccessible(true);
        $transaction = $ref->getValue($browser);

        $ref = new \ReflectionProperty($transaction, 'loop');
        $ref->setAccessible(true);
        $loop = $ref->getValue($transaction);

        $this->assertSame($this->loop, $loop);
    }

    public function testConstructWithLoopWithLegacySignatureAssignsGivenLoop()
    {
        $browser = new Browser($this->loop);

        $ref = new \ReflectionProperty($browser, 'transaction');
        $ref->setAccessible(true);
        $transaction = $ref->getValue($browser);

        $ref = new \ReflectionProperty($transaction, 'loop');
        $ref->setAccessible(true);
        $loop = $ref->getValue($transaction);

        $this->assertSame($this->loop, $loop);
    }

    public function testConstructWithInvalidConnectorThrows()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Browser('foo');
    }

    public function testConstructWithInvalidLoopThrows()
    {
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();

        $this->setExpectedException('InvalidArgumentException');
        new Browser($connector, 'foo');
    }

    public function testConstructWithConnectorTwiceThrows()
    {
        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();

        $this->setExpectedException('InvalidArgumentException');
        new Browser($connector, $connector);
    }

    public function testConstructWithLoopTwiceThrows()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Browser($this->loop, $this->loop);
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
            'slash returns base without path' => array(
                'http://example.com/base',
                '/',
                'http://example.com/',
            ),
            'relative is added behind base' => array(
                'http://example.com/base/',
                'test',
                'http://example.com/base/test',
            ),
            'relative is added behind base without path' => array(
                'http://example.com/base',
                'test',
                'http://example.com/test',
            ),
            'relative level up is added behind parent path' => array(
                'http://example.com/base/foo/',
                '../bar',
                'http://example.com/base/bar',
            ),
            'absolute with slash is added behind base without path' => array(
                'http://example.com/base',
                '/test',
                'http://example.com/test',
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
            'query string with slash is added behind base without path' => array(
                'http://example.com/base',
                '/?key=value',
                'http://example.com/?key=value',
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

        $this->browser = new Browser($connector, $this->loop);

        $promise = $this->browser->get('http://example.com/');
        $promise->cancel();
    }

    public function testWithHeaderShouldOverwriteExistingHeader()
    {
        $this->browser = $this->browser->withHeader('User-Agent', 'ACMC'); //should be overwritten
        $this->browser = $this->browser->withHeader('user-agent', 'ABC'); //should be the user-agent

        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $that->assertEquals(array('ABC'), $request->getHeader('UsEr-AgEnT'));
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/');
    }

    public function testWithHeaderShouldBeOverwrittenByExplicitHeaderInGetMethod()
    {
        $this->browser = $this->browser->withHeader('User-Agent', 'ACMC');

        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $that->assertEquals(array('ABC'), $request->getHeader('UsEr-AgEnT'));
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/', array('user-Agent' => 'ABC')); //should win
    }

    public function testWithMultipleHeadersShouldBeMergedCorrectlyWithMultipleDefaultHeaders()
    {
        $this->browser = $this->browser->withHeader('User-Agent', 'ACMC');
        $this->browser = $this->browser->withHeader('User-Test', 'Test');
        $this->browser = $this->browser->withHeader('Custom-HEADER', 'custom');
        $this->browser = $this->browser->withHeader('just-a-header', 'header-value');

        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $expectedHeaders = array(
                'Host' => array('example.com'),

                'User-Test' => array('Test'),
                'just-a-header' => array('header-value'),

                'user-Agent' => array('ABC'),
                'another-header' => array('value'),
                'custom-header' => array('data'),
            );

            $that->assertEquals($expectedHeaders, $request->getHeaders());
            return true;
        }))->willReturn(new Promise(function () { }));

        $headers = array(
            'user-Agent' => 'ABC', //should overwrite: 'User-Agent', 'ACMC'
            'another-header' => 'value',
            'custom-header' => 'data', //should overwrite: 'Custom-header', 'custom'
        );
        $this->browser->get('http://example.com/', $headers);
    }

    public function testWithoutHeaderShouldRemoveExistingHeader()
    {
        $this->browser = $this->browser->withHeader('User-Agent', 'ACMC');
        $this->browser = $this->browser->withoutHeader('UsEr-AgEnT'); //should remove case-insensitive header

        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $that->assertEquals(array(), $request->getHeader('user-agent'));
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/');
    }

    public function testBrowserShouldSendDefaultUserAgentHeader()
    {
        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $that->assertEquals(array(0 => 'ReactPHP/1'), $request->getHeader('user-agent'));
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/');
    }

    public function testBrowserShouldNotSendDefaultUserAgentHeaderIfWithoutHeaderRemovesUserAgent()
    {
        $this->browser = $this->browser->withoutHeader('UsEr-AgEnT');

        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($that) {
            $that->assertEquals(array(), $request->getHeader('User-Agent'));
            return true;
        }))->willReturn(new Promise(function () { }));

        $this->browser->get('http://example.com/');
    }
}
