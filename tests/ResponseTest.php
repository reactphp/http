<?php

namespace React\Tests\Http;

use React\Http\Response;
use React\Stream\WritableStream;

class ResponseTest extends TestCase
{
    public function testResponseShouldBeChunkedByDefault()
    {
        $expected = '';
        $expected .= "HTTP/1.1 200 OK\r\n";
        $expected .= "X-Powered-By: React/alpha\r\n";
        $expected .= "Transfer-Encoding: chunked\r\n";
        $expected .= "Connection: close\r\n";
        $expected .= "\r\n";

        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->once())
            ->method('write')
            ->with($expected);

        $response = new Response($conn);
        $response->writeHead();
    }

    public function testResponseShouldNotBeChunkedWhenProtocolVersionIsNot11()
    {
        $expected = '';
        $expected .= "HTTP/1.0 200 OK\r\n";
        $expected .= "X-Powered-By: React/alpha\r\n";
        $expected .= "\r\n";

        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->once())
            ->method('write')
            ->with($expected);

        $response = new Response($conn, '1.0');
        $response->writeHead();
    }

    public function testResponseShouldBeChunkedEvenWithOtherTransferEncoding()
    {
        $expected = '';
        $expected .= "HTTP/1.1 200 OK\r\n";
        $expected .= "X-Powered-By: React/alpha\r\n";
        $expected .= "Transfer-Encoding: chunked\r\n";
        $expected .= "Connection: close\r\n";
        $expected .= "\r\n";

        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->once())
            ->method('write')
            ->with($expected);

        $response = new Response($conn);
        $response->writeHead(200, array('transfer-encoding' => 'custom'));
    }

    public function testResponseShouldNotBeChunkedWithContentLength()
    {
        $expected = '';
        $expected .= "HTTP/1.1 200 OK\r\n";
        $expected .= "X-Powered-By: React/alpha\r\n";
        $expected .= "Content-Length: 22\r\n";
        $expected .= "Connection: close\r\n";
        $expected .= "\r\n";

        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->once())
            ->method('write')
            ->with($expected);

        $response = new Response($conn);
        $response->writeHead(200, array('Content-Length' => 22));
    }

    public function testResponseShouldNotBeChunkedWithContentLengthCaseInsensitive()
    {
        $expected = '';
        $expected .= "HTTP/1.1 200 OK\r\n";
        $expected .= "X-Powered-By: React/alpha\r\n";
        $expected .= "CONTENT-LENGTH: 0\r\n";
        $expected .= "Connection: close\r\n";
        $expected .= "\r\n";

        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->once())
            ->method('write')
            ->with($expected);

        $response = new Response($conn);
        $response->writeHead(200, array('CONTENT-LENGTH' => 0));
    }

    public function testResponseShouldIncludeCustomByPoweredAsFirstHeaderIfGivenExplicitly()
    {
        $expected = '';
        $expected .= "HTTP/1.1 200 OK\r\n";
        $expected .= "Content-Length: 0\r\n";
        $expected .= "X-POWERED-BY: demo\r\n";
        $expected .= "Connection: close\r\n";
        $expected .= "\r\n";

        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->once())
            ->method('write')
            ->with($expected);

        $response = new Response($conn);
        $response->writeHead(200, array('Content-Length' => 0, 'X-POWERED-BY' => 'demo'));
    }

    public function testResponseShouldNotIncludePoweredByIfGivenEmptyArray()
    {
        $expected = '';
        $expected .= "HTTP/1.1 200 OK\r\n";
        $expected .= "Content-Length: 0\r\n";
        $expected .= "Connection: close\r\n";
        $expected .= "\r\n";

        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->once())
            ->method('write')
            ->with($expected);

        $response = new Response($conn);
        $response->writeHead(200, array('Content-Length' => 0, 'X-Powered-By' => array()));
    }

    public function testResponseShouldAlwaysIncludeConnectionCloseIrrespectiveOfExplicitValue()
    {
        $expected = '';
        $expected .= "HTTP/1.1 200 OK\r\n";
        $expected .= "X-Powered-By: React/alpha\r\n";
        $expected .= "Content-Length: 0\r\n";
        $expected .= "Connection: close\r\n";
        $expected .= "\r\n";

        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->once())
            ->method('write')
            ->with($expected);

        $response = new Response($conn);
        $response->writeHead(200, array('Content-Length' => 0, 'connection' => 'ignored'));
    }

    /** @expectedException Exception */
    public function testWriteHeadTwiceShouldThrowException()
    {
        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->once())
            ->method('write');

        $response = new Response($conn);
        $response->writeHead();
        $response->writeHead();
    }

    public function testEndWithoutDataWritesEndChunkAndEndsInput()
    {
        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->at(4))
            ->method('write')
            ->with("0\r\n\r\n");
        $conn
            ->expects($this->once())
            ->method('end');

        $response = new Response($conn);
        $response->writeHead();
        $response->end();
    }

    public function testEndWithDataWritesToInputAndEndsInputWithoutData()
    {
        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->at(4))
            ->method('write')
            ->with("3\r\nbye\r\n");
        $conn
            ->expects($this->at(5))
            ->method('write')
            ->with("0\r\n\r\n");
        $conn
            ->expects($this->once())
            ->method('end');

        $response = new Response($conn);
        $response->writeHead();
        $response->end('bye');
    }

    public function testEndWithoutDataWithoutChunkedEncodingWritesNoDataAndEndsInput()
    {
        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->once())
            ->method('write');
        $conn
            ->expects($this->once())
            ->method('end');

        $response = new Response($conn);
        $response->writeHead(200, array('Content-Length' => 0));
        $response->end();
    }

    /** @expectedException Exception */
    public function testEndWithoutHeadShouldThrowException()
    {
        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->never())
            ->method('end');

        $response = new Response($conn);
        $response->end();
    }

    /** @expectedException Exception */
    public function testWriteWithoutHeadShouldThrowException()
    {
        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->never())
            ->method('write');

        $response = new Response($conn);
        $response->write('test');
    }

    public function testResponseBodyShouldBeChunkedCorrectly()
    {
        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->at(4))
            ->method('write')
            ->with("5\r\nHello\r\n");
        $conn
            ->expects($this->at(5))
            ->method('write')
            ->with("1\r\n \r\n");
        $conn
            ->expects($this->at(6))
            ->method('write')
            ->with("6\r\nWorld\n\r\n");
        $conn
            ->expects($this->at(7))
            ->method('write')
            ->with("0\r\n\r\n");

        $response = new Response($conn);
        $response->writeHead();

        $response->write('Hello');
        $response->write(' ');
        $response->write("World\n");
        $response->end();
    }

    public function testResponseBodyShouldSkipEmptyChunks()
    {
        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->at(4))
            ->method('write')
            ->with("5\r\nHello\r\n");
        $conn
            ->expects($this->at(5))
            ->method('write')
            ->with("5\r\nWorld\r\n");
        $conn
            ->expects($this->at(6))
            ->method('write')
            ->with("0\r\n\r\n");

        $response = new Response($conn);
        $response->writeHead();

        $response->write('Hello');
        $response->write('');
        $response->write('World');
        $response->end();
    }

    /** @test */
    public function writeContinueShouldSendContinueLineBeforeRealHeaders()
    {
        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->at(3))
            ->method('write')
            ->with("HTTP/1.1 100 Continue\r\n\r\n");
        $conn
            ->expects($this->at(4))
            ->method('write')
            ->with($this->stringContains("HTTP/1.1 200 OK\r\n"));

        $response = new Response($conn);
        $response->writeContinue();
        $response->writeHead();
    }

    /**
     * @test
     * @expectedException Exception
     */
    public function writeContinueShouldThrowForHttp10()
    {
        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();

        $response = new Response($conn, '1.0');
        $response->writeContinue();
    }

    /** @expectedException Exception */
    public function testWriteContinueAfterWriteHeadShouldThrowException()
    {
        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->once())
            ->method('write');

        $response = new Response($conn);
        $response->writeHead();
        $response->writeContinue();
    }

    /** @test */
    public function shouldRemoveNewlinesFromHeaders()
    {
        $expected = '';
        $expected .= "HTTP/1.1 200 OK\r\n";
        $expected .= "X-Powered-By: React/alpha\r\n";
        $expected .= "FooBar: BazQux\r\n";
        $expected .= "Transfer-Encoding: chunked\r\n";
        $expected .= "Connection: close\r\n";
        $expected .= "\r\n";

        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->once())
            ->method('write')
            ->with($expected);

        $response = new Response($conn);
        $response->writeHead(200, array("Foo\nBar" => "Baz\rQux"));
    }

    /** @test */
    public function missingStatusCodeTextShouldResultInNumberOnlyStatus()
    {
        $expected = '';
        $expected .= "HTTP/1.1 700 \r\n";
        $expected .= "X-Powered-By: React/alpha\r\n";
        $expected .= "Transfer-Encoding: chunked\r\n";
        $expected .= "Connection: close\r\n";
        $expected .= "\r\n";

        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->once())
            ->method('write')
            ->with($expected);

        $response = new Response($conn);
        $response->writeHead(700);
    }

    /** @test */
    public function shouldAllowArrayHeaderValues()
    {
        $expected = '';
        $expected .= "HTTP/1.1 200 OK\r\n";
        $expected .= "X-Powered-By: React/alpha\r\n";
        $expected .= "Set-Cookie: foo=bar\r\n";
        $expected .= "Set-Cookie: bar=baz\r\n";
        $expected .= "Transfer-Encoding: chunked\r\n";
        $expected .= "Connection: close\r\n";
        $expected .= "\r\n";

        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->once())
            ->method('write')
            ->with($expected);

        $response = new Response($conn);
        $response->writeHead(200, array("Set-Cookie" => array("foo=bar", "bar=baz")));
    }

    /** @test */
    public function shouldIgnoreHeadersWithNullValues()
    {
        $expected = '';
        $expected .= "HTTP/1.1 200 OK\r\n";
        $expected .= "X-Powered-By: React/alpha\r\n";
        $expected .= "Transfer-Encoding: chunked\r\n";
        $expected .= "Connection: close\r\n";
        $expected .= "\r\n";

        $conn = $this
            ->getMockBuilder('React\Socket\ConnectionInterface')
            ->getMock();
        $conn
            ->expects($this->once())
            ->method('write')
            ->with($expected);

        $response = new Response($conn);
        $response->writeHead(200, array("FooBar" => null));
    }

    public function testCloseClosesInputAndEmitsCloseEvent()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('close');

        $response = new Response($input);

        $response->on('close', $this->expectCallableOnce());

        $response->close();
    }

    public function testClosingInputEmitsCloseEvent()
    {
        $input = new WritableStream();
        $response = new Response($input);

        $response->on('close', $this->expectCallableOnce());

        $input->close();
    }

    public function testCloseMultipleTimesEmitsCloseEventOnce()
    {
        $input = new WritableStream();
        $response = new Response($input);

        $response->on('close', $this->expectCallableOnce());

        $response->close();
        $response->close();
    }

    public function testIsNotWritableAfterClose()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $response = new Response($input);

        $response->close();

        $this->assertFalse($response->isWritable());
    }

    public function testCloseAfterEndIsPassedThrough()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('end');
        $input->expects($this->once())->method('close');

        $response = new Response($input);

        $response->writeHead();
        $response->end();
        $response->close();
    }

    public function testWriteAfterCloseIsNoOp()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('close');
        $input->expects($this->never())->method('write');

        $response = new Response($input);
        $response->close();

        $this->assertFalse($response->write('noop'));
    }

    public function testWriteHeadAfterCloseIsNoOp()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('close');
        $input->expects($this->never())->method('write');

        $response = new Response($input);
        $response->close();

        $response->writeHead();
    }

    public function testWriteContinueAfterCloseIsNoOp()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('close');
        $input->expects($this->never())->method('write');

        $response = new Response($input);
        $response->close();

        $response->writeContinue();
    }

    public function testEndAfterCloseIsNoOp()
    {
        $input = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $input->expects($this->once())->method('close');
        $input->expects($this->never())->method('write');
        $input->expects($this->never())->method('end');

        $response = new Response($input);
        $response->close();

        $response->end('noop');
    }

    public function testErrorEventShouldBeForwardedWithoutClosing()
    {
        $input = new WritableStream();
        $response = new Response($input);

        $response->on('error', $this->expectCallableOnce());
        $response->on('close', $this->expectCallableNever());

        $input->emit('error', array(new \RuntimeException()));
    }

    public function testDrainEventShouldBeForwarded()
    {
        $input = new WritableStream();
        $response = new Response($input);

        $response->on('drain', $this->expectCallableOnce());

        $input->emit('drain');
    }
}
