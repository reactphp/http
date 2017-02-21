<?php

namespace React\Tests\Http;

use React\Http\Request;
use RingCentral\Psr7\Request as Psr;

class RequestTest extends TestCase
{
    /** @test */
    public function expectsContinueShouldBeFalseByDefault()
    {
        $headers = array();
        $request = new Request(new Psr('GET', '/', $headers, null, '1.1'));

        $this->assertFalse($request->expectsContinue());
    }

    /** @test */
    public function expectsContinueShouldBeTrueIfContinueExpected()
    {
        $headers = array('Expect' => array('100-continue'));
        $request = new Request(new Psr('GET', '/', $headers, null, '1.1'));

        $this->assertTrue($request->expectsContinue());
    }

    /** @test */
    public function expectsContinueShouldBeTrueIfContinueExpectedCaseInsensitive()
    {
        $headers = array('EXPECT' => array('100-CONTINUE'));
        $request = new Request(new Psr('GET', '/', $headers, null, '1.1'));

        $this->assertTrue($request->expectsContinue());
    }

    /** @test */
    public function expectsContinueShouldBeFalseForHttp10()
    {
        $headers = array('Expect' => array('100-continue'));
        $request = new Request(new Psr('GET', '/', $headers, null, '1.0'));

        $this->assertFalse($request->expectsContinue());
    }

    public function testEmptyHeader()
    {
        $request = new Request(new Psr('GET', '/', array()));

        $this->assertEquals(array(), $request->getHeaders());
        $this->assertFalse($request->hasHeader('Test'));
        $this->assertEquals(array(), $request->getHeader('Test'));
        $this->assertEquals('', $request->getHeaderLine('Test'));
    }

    public function testHeaderIsCaseInsensitive()
    {
        $request = new Request(new Psr('GET', '/', array(
            'TEST' => array('Yes'),
        )));

        $this->assertEquals(array('TEST' => array('Yes')), $request->getHeaders());
        $this->assertTrue($request->hasHeader('Test'));
        $this->assertEquals(array('Yes'), $request->getHeader('Test'));
        $this->assertEquals('Yes', $request->getHeaderLine('Test'));
    }

    public function testHeaderWithMultipleValues()
    {
        $request = new Request(new Psr('GET', '/', array(
            'Test' => array('a', 'b'),
        )));

        $this->assertEquals(array('Test' => array('a', 'b')), $request->getHeaders());
        $this->assertTrue($request->hasHeader('Test'));
        $this->assertEquals(array('a', 'b'), $request->getHeader('Test'));
        $this->assertEquals('a, b', $request->getHeaderLine('Test'));
    }

    public function testCloseEmitsCloseEvent()
    {
        $request = new Request(new Psr('GET', '/'));

        $request->on('close', $this->expectCallableOnce());

        $request->close();
    }

    public function testCloseMultipleTimesEmitsCloseEventOnce()
    {
        $request = new Request(new Psr('GET', '/'));

        $request->on('close', $this->expectCallableOnce());

        $request->close();
        $request->close();
    }

    public function testIsNotReadableAfterClose()
    {
        $request = new Request(new Psr('GET', '/'));

        $request->close();

        $this->assertFalse($request->isReadable());
    }

    public function testPipeReturnsDest()
    {
        $dest = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $request = new Request(new Psr('GET', '/'));

        $ret = $request->pipe($dest);

        $this->assertSame($dest, $ret);
    }
}
