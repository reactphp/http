<?php

namespace React\Tests\Http\Psr\Http;

use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use React\Http\Io\ServerRequest;
use React\Http\Psr\Http\MiddlewareStackHandler;
use React\Http\Response;
use React\Tests\Http\CallCountStubHandler;
use React\Tests\Http\CallCountStubMiddleware;

class MiddlewareStackHandlerTest extends TestCase
{
    /**
     * @requires PHP 7.0
     *
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage All middlewares in the stack must implement the Psr\Http\Server\MiddlewareInterface.
     */
	public function testConstructorThrowsExceptionWhenInvalidMiddlewareIsPassed()
	{
        new MiddlewareStackHandler(
            new CallCountStubHandler(new Response()),
            array('handler')
        );
	}

    /**
     * @requires PHP 7.0
     */
	public function testCompleteStackIsHandled()
	{
		$response = new Response();
		$handler = new CallCountStubHandler($response);
		$request = new ServerRequest('GET', 'http://localhost:8080/');

		$middlewares = array(
			new CallCountStubMiddleware(),
			new CallCountStubMiddleware(),
		);

		$middlewareStackHandler = new MiddlewareStackHandler(
			$handler,
			$middlewares
		);

		$responseFromStack = $middlewareStackHandler->handle($request);

		self::assertSame($response, $responseFromStack);
		self::assertSame(1, $middlewares[0]->getCallCount());
		self::assertSame(1, $middlewares[1]->getCallCount());
        self::assertSame(1, $handler->getCallCount());
	}
}
