<?php

namespace React\Tests\Http\Message;

use React\Http\Message\Uri;
use React\Tests\Http\TestCase;

class UriTest extends TestCase
{
    public function testCtorWithInvalidSyntaxThrows()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Uri('///');
    }

    public function testCtorWithInvalidSchemeThrows()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Uri('not+a+scheme://localhost');
    }

    public function testCtorWithInvalidHostThrows()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Uri('http://not a host/');
    }

    public function testCtorWithInvalidPortThrows()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Uri('http://localhost:80000/');
    }

    public static function provideValidUris()
    {
        return array(
            array(
                'http://localhost'
            ),
            array(
                'http://localhost/'
            ),
            array(
                'http://localhost:8080/'
            ),
            array(
                'http://127.0.0.1/'
            ),
            array(
                'http://[::1]:8080/'
            ),
            array(
                'http://localhost/path'
            ),
            array(
                'http://localhost/sub/path'
            ),
            array(
                'http://localhost/with%20space'
            ),
            array(
                'http://localhost/with%2fslash'
            ),
            array(
                'http://localhost/?name=Alice'
            ),
            array(
                'http://localhost/?name=John+Doe'
            ),
            array(
                'http://localhost/?name=John%20Doe'
            ),
            array(
                'http://localhost/?name=Alice&age=42'
            ),
            array(
                'http://localhost/?name=Alice&'
            ),
            array(
                'http://localhost/?choice=A%26B'
            ),
            array(
                'http://localhost/?safe=Yes!?'
            ),
            array(
                'http://localhost/?alias=@home'
            ),
            array(
                'http://localhost/?assign:=true'
            ),
            array(
                'http://localhost/?name='
            ),
            array(
                'http://localhost/?name'
            ),
            array(
                ''
            ),
            array(
                '/'
            ),
            array(
                '/path'
            ),
            array(
                'path'
            ),
            array(
                'http://user@localhost/'
            ),
            array(
                'http://user:@localhost/'
            ),
            array(
                'http://:pass@localhost/'
            ),
            array(
                'http://user:pass@localhost/path?query#fragment'
            ),
            array(
                'http://user%20name:pass%20word@localhost/path%20name?query%20name#frag%20ment'
            )
        );
    }

    /**
     * @dataProvider provideValidUris
     * @param string $string
     */
    public function testToStringReturnsOriginalUriGivenToCtor($string)
    {
        if (PHP_VERSION_ID < 50519 || (PHP_VERSION_ID < 50603 && PHP_VERSION_ID >= 50606)) {
            // @link https://3v4l.org/HdoPG
            $this->markTestSkipped('Empty password not supported on legacy PHP');
        }

        $uri = new Uri($string);

        $this->assertEquals($string, (string) $uri);
    }

    public static function provideValidUrisThatWillBeTransformed()
    {
        return array(
            array(
                'http://localhost:8080/?',
                'http://localhost:8080/'
            ),
            array(
                'http://localhost:8080/#',
                'http://localhost:8080/'
            ),
            array(
                'http://localhost:8080/?#',
                'http://localhost:8080/'
            ),
            array(
                'http://@localhost:8080/',
                'http://localhost:8080/'
            ),
            array(
                'http://localhost:8080/?percent=50%',
                'http://localhost:8080/?percent=50%25'
            ),
            array(
                'http://user name:pass word@localhost/path name?query name#frag ment',
                'http://user%20name:pass%20word@localhost/path%20name?query%20name#frag%20ment'
            ),
            array(
                'HTTP://USER:PASS@LOCALHOST:8080/PATH?QUERY#FRAGMENT',
                'http://USER:PASS@localhost:8080/PATH?QUERY#FRAGMENT'
            )
        );
    }

    /**
     * @dataProvider provideValidUrisThatWillBeTransformed
     * @param string $string
     * @param string $escaped
     */
    public function testToStringReturnsTransformedUriFromUriGivenToCtor($string, $escaped = null)
    {
        $uri = new Uri($string);

        $this->assertEquals($escaped, (string) $uri);
    }

    public function testToStringReturnsUriWithPathPrefixedWithSlashWhenPathDoesNotStartWithSlash()
    {
        $uri = new Uri('http://localhost:8080');
        $uri = $uri->withPath('path');

        $this->assertEquals('http://localhost:8080/path', (string) $uri);
    }

    public function testWithSchemeReturnsNewInstanceWhenSchemeIsChanged()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withScheme('https');
        $this->assertNotSame($uri, $new);
        $this->assertEquals('https', $new->getScheme());
        $this->assertEquals('http', $uri->getScheme());
    }

    public function testWithSchemeReturnsNewInstanceWithSchemeToLowerCaseWhenSchemeIsChangedWithUpperCase()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withScheme('HTTPS');
        $this->assertNotSame($uri, $new);
        $this->assertEquals('https', $new->getScheme());
        $this->assertEquals('http', $uri->getScheme());
    }

    public function testWithSchemeReturnsNewInstanceWithDefaultPortRemovedWhenSchemeIsChangedToDefaultPortForHttp()
    {
        $uri = new Uri('https://localhost:80');

        $new = $uri->withScheme('http');
        $this->assertNotSame($uri, $new);
        $this->assertNull($new->getPort());
        $this->assertEquals(80, $uri->getPort());
    }

    public function testWithSchemeReturnsNewInstanceWithDefaultPortRemovedWhenSchemeIsChangedToDefaultPortForHttps()
    {
        $uri = new Uri('http://localhost:443');

        $new = $uri->withScheme('https');
        $this->assertNotSame($uri, $new);
        $this->assertNull($new->getPort());
        $this->assertEquals(443, $uri->getPort());
    }

    public function testWithSchemeReturnsSameInstanceWhenSchemeIsUnchanged()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withScheme('http');
        $this->assertSame($uri, $new);
        $this->assertEquals('http', $uri->getScheme());
    }

    public function testWithSchemeReturnsSameInstanceWhenSchemeToLowerCaseIsUnchanged()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withScheme('HTTP');
        $this->assertSame($uri, $new);
        $this->assertEquals('http', $uri->getScheme());
    }

    public function testWithSchemeThrowsWhenSchemeIsInvalid()
    {
        $uri = new Uri('http://localhost');

        $this->setExpectedException('InvalidArgumentException');
        $uri->withScheme('invalid+scheme');
    }

    public function testWithUserInfoReturnsNewInstanceWhenUserInfoIsChangedWithNameAndPassword()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withUserInfo('user', 'pass');
        $this->assertNotSame($uri, $new);
        $this->assertEquals('user:pass', $new->getUserInfo());
        $this->assertEquals('', $uri->getUserInfo());
    }

    public function testWithUserInfoReturnsNewInstanceWhenUserInfoIsChangedWithNameOnly()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withUserInfo('user');
        $this->assertNotSame($uri, $new);
        $this->assertEquals('user', $new->getUserInfo());
        $this->assertEquals('', $uri->getUserInfo());
    }

    public function testWithUserInfoReturnsNewInstanceWhenUserInfoIsChangedWithNameAndEmptyPassword()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withUserInfo('user', '');
        $this->assertNotSame($uri, $new);
        $this->assertEquals('user:', $new->getUserInfo());
        $this->assertEquals('', $uri->getUserInfo());
    }

    public function testWithUserInfoReturnsNewInstanceWhenUserInfoIsChangedWithPasswordOnly()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withUserInfo('', 'pass');
        $this->assertNotSame($uri, $new);
        $this->assertEquals(':pass', $new->getUserInfo());
        $this->assertEquals('', $uri->getUserInfo());
    }

    public function testWithUserInfoReturnsNewInstanceWhenUserInfoIsChangedWithNameAndPasswordEncoded()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withUserInfo('user:alice', 'pass%20word');
        $this->assertNotSame($uri, $new);
        $this->assertEquals('user%3Aalice:pass%20word', $new->getUserInfo());
        $this->assertEquals('', $uri->getUserInfo());
    }

    public function testWithSchemeReturnsSameInstanceWhenSchemeIsUnchangedEmpty()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withUserInfo('');
        $this->assertSame($uri, $new);
        $this->assertEquals('', $uri->getUserInfo());
    }

    public function testWithSchemeReturnsSameInstanceWhenSchemeIsUnchangedWithNameAndPassword()
    {
        $uri = new Uri('http://user:pass@localhost');

        $new = $uri->withUserInfo('user', 'pass');
        $this->assertSame($uri, $new);
        $this->assertEquals('user:pass', $uri->getUserInfo());
    }

    public function testWithHostReturnsNewInstanceWhenHostIsChanged()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withHost('example.com');
        $this->assertNotSame($uri, $new);
        $this->assertEquals('example.com', $new->getHost());
        $this->assertEquals('localhost', $uri->getHost());
    }

    public function testWithHostReturnsNewInstanceWithHostToLowerCaseWhenHostIsChangedWithUpperCase()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withHost('EXAMPLE.COM');
        $this->assertNotSame($uri, $new);
        $this->assertEquals('example.com', $new->getHost());
        $this->assertEquals('localhost', $uri->getHost());
    }

    public function testWithHostReturnsNewInstanceWhenHostIsChangedToEmptyString()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withHost('');
        $this->assertNotSame($uri, $new);
        $this->assertEquals('', $new->getHost());
        $this->assertEquals('localhost', $uri->getHost());
    }

    public function testWithHostReturnsSameInstanceWhenHostIsUnchanged()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withHost('localhost');
        $this->assertSame($uri, $new);
        $this->assertEquals('localhost', $uri->getHost());
    }

    public function testWithHostReturnsSameInstanceWhenHostToLowerCaseIsUnchanged()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withHost('LOCALHOST');
        $this->assertSame($uri, $new);
        $this->assertEquals('localhost', $uri->getHost());
    }

    public function testWithHostThrowsWhenHostIsInvalidWithPlus()
    {
        $uri = new Uri('http://localhost');

        $this->setExpectedException('InvalidArgumentException');
        $uri->withHost('invalid+host');
    }

    public function testWithHostThrowsWhenHostIsInvalidWithSpace()
    {
        $uri = new Uri('http://localhost');

        $this->setExpectedException('InvalidArgumentException');
        $uri->withHost('invalid host');
    }

    public function testWithPortReturnsNewInstanceWhenPortIsChanged()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withPort(8080);
        $this->assertNotSame($uri, $new);
        $this->assertEquals(8080, $new->getPort());
        $this->assertNull($uri->getPort());
    }

    public function testWithPortReturnsNewInstanceWithDefaultPortRemovedWhenPortIsChangedToDefaultPortForHttp()
    {
        $uri = new Uri('http://localhost:8080');

        $new = $uri->withPort(80);
        $this->assertNotSame($uri, $new);
        $this->assertNull($new->getPort());
        $this->assertEquals(8080, $uri->getPort());
    }

    public function testWithPortReturnsNewInstanceWithDefaultPortRemovedWhenPortIsChangedToDefaultPortForHttps()
    {
        $uri = new Uri('https://localhost:8080');

        $new = $uri->withPort(443);
        $this->assertNotSame($uri, $new);
        $this->assertNull($new->getPort());
        $this->assertEquals(8080, $uri->getPort());
    }

    public function testWithPortReturnsSameInstanceWhenPortIsUnchanged()
    {
        $uri = new Uri('http://localhost:8080');

        $new = $uri->withPort(8080);
        $this->assertSame($uri, $new);
        $this->assertEquals(8080, $uri->getPort());
    }

    public function testWithPortReturnsSameInstanceWhenPortIsUnchangedDefaultPortForHttp()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withPort(80);
        $this->assertSame($uri, $new);
        $this->assertNull($uri->getPort());
    }

    public function testWithPortReturnsSameInstanceWhenPortIsUnchangedDefaultPortForHttps()
    {
        $uri = new Uri('https://localhost');

        $new = $uri->withPort(443);
        $this->assertSame($uri, $new);
        $this->assertNull($uri->getPort());
    }

    public function testWithPortThrowsWhenPortIsInvalidUnderflow()
    {
        $uri = new Uri('http://localhost');

        $this->setExpectedException('InvalidArgumentException');
        $uri->withPort(0);
    }

    public function testWithPortThrowsWhenPortIsInvalidOverflow()
    {
        $uri = new Uri('http://localhost');

        $this->setExpectedException('InvalidArgumentException');
        $uri->withPort(65536);
    }

    public function testWithPathReturnsNewInstanceWhenPathIsChanged()
    {
        $uri = new Uri('http://localhost/');

        $new = $uri->withPath('/path');
        $this->assertNotSame($uri, $new);
        $this->assertEquals('/path', $new->getPath());
        $this->assertEquals('/', $uri->getPath());
    }

    public function testWithPathReturnsNewInstanceWhenPathIsChangedEncoded()
    {
        $uri = new Uri('http://localhost/');

        $new = $uri->withPath('/a new/path%20here!');
        $this->assertNotSame($uri, $new);
        $this->assertEquals('/a%20new/path%20here!', $new->getPath());
        $this->assertEquals('/', $uri->getPath());
    }

    public function testWithPathReturnsSameInstanceWhenPathIsUnchanged()
    {
        $uri = new Uri('http://localhost/path');

        $new = $uri->withPath('/path');
        $this->assertSame($uri, $new);
        $this->assertEquals('/path', $uri->getPath());
    }

    public function testWithPathReturnsSameInstanceWhenPathIsUnchangedEncoded()
    {
        $uri = new Uri('http://localhost/a%20new/path%20here!');

        $new = $uri->withPath('/a new/path%20here!');
        $this->assertSame($uri, $new);
        $this->assertEquals('/a%20new/path%20here!', $uri->getPath());
    }

    public function testWithQueryReturnsNewInstanceWhenQueryIsChanged()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withQuery('foo=bar');
        $this->assertNotSame($uri, $new);
        $this->assertEquals('foo=bar', $new->getQuery());
        $this->assertEquals('', $uri->getQuery());
    }

    public function testWithQueryReturnsNewInstanceWhenQueryIsChangedEncoded()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withQuery('foo=a new%20text!');
        $this->assertNotSame($uri, $new);
        $this->assertEquals('foo=a%20new%20text!', $new->getQuery());
        $this->assertEquals('', $uri->getQuery());
    }

    public function testWithQueryReturnsSameInstanceWhenQueryIsUnchanged()
    {
        $uri = new Uri('http://localhost?foo=bar');

        $new = $uri->withQuery('foo=bar');
        $this->assertSame($uri, $new);
        $this->assertEquals('foo=bar', $uri->getQuery());
    }

    public function testWithQueryReturnsSameInstanceWhenQueryIsUnchangedEncoded()
    {
        $uri = new Uri('http://localhost?foo=a%20new%20text!');

        $new = $uri->withQuery('foo=a new%20text!');
        $this->assertSame($uri, $new);
        $this->assertEquals('foo=a%20new%20text!', $uri->getQuery());
    }

    public function testWithFragmentReturnsNewInstanceWhenFragmentIsChanged()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withFragment('section');
        $this->assertNotSame($uri, $new);
        $this->assertEquals('section', $new->getFragment());
        $this->assertEquals('', $uri->getFragment());
    }

    public function testWithFragmentReturnsNewInstanceWhenFragmentIsChangedEncoded()
    {
        $uri = new Uri('http://localhost');

        $new = $uri->withFragment('section new%20text!');
        $this->assertNotSame($uri, $new);
        $this->assertEquals('section%20new%20text!', $new->getFragment());
        $this->assertEquals('', $uri->getFragment());
    }

    public function testWithFragmentReturnsSameInstanceWhenFragmentIsUnchanged()
    {
        $uri = new Uri('http://localhost#section');

        $new = $uri->withFragment('section');
        $this->assertSame($uri, $new);
        $this->assertEquals('section', $uri->getFragment());
    }

    public function testWithFragmentReturnsSameInstanceWhenFragmentIsUnchangedEncoded()
    {
        $uri = new Uri('http://localhost#section%20new%20text!');

        $new = $uri->withFragment('section new%20text!');
        $this->assertSame($uri, $new);
        $this->assertEquals('section%20new%20text!', $uri->getFragment());
    }
}
