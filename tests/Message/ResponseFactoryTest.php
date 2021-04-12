<?php

namespace React\Tests\Http\Message;

use React\Http\Io\HttpBodyStream;
use React\Http\Message\Response;
use React\Http\Message\ResponseFactory;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;

class ResponseFactoryTest extends TestCase
{
    public function testHtml()
    {
        $body = '<html><body>Hello world!</body></html>';
        $response = ResponseFactory::html($body);

        self::assertSame($body, $response->getBody()->getContents());
    }

    public function provideJson()
    {
        return array(
            'string' => array(
                'Hello World!',
                '"Hello World!"',
            ),
            'array' => array(
                array(
                    'message' => array(
                        'body' => 'Hello World!'
                    )
                ),
                '{"message":{"body":"Hello World!"}}',
            ),
        );
    }

    /**
     * @dataProvider provideJson
     */
    public function testJson($json, $expectedBody)
    {
        $response = ResponseFactory::json($json);

        self::assertSame($expectedBody, $response->getBody()->getContents());
    }

    public function testInvalidJson()
    {
        $this->setExpectedException('InvalidArgumentException');

        ResponseFactory::json("\xB1\x31");
    }

    public function testPlain()
    {
        $body = 'Hello world!';
        $response = ResponseFactory::plain($body);

        self::assertSame($body, $response->getBody()->getContents());
    }

    public function testXml()
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?><message><body>Hello world!</body></message>';
        $response = ResponseFactory::xml($body);

        self::assertSame($body, $response->getBody()->getContents());
    }
}
