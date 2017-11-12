<?php

namespace React\Tests\Http;

use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function expectCallableExactly($amount)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->exactly($amount))
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableOnce()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableOnceWith($value)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($value);

        return $mock;
    }

    protected function expectCallableNever()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableConsecutive($numberOfCalls, array $with)
    {
        $mock = $this->createCallableMock();

        for ($i = 0; $i < $numberOfCalls; $i++) {
            $mock
                ->expects($this->at($i))
                ->method('__invoke')
                ->with($this->equalTo($with[$i]));
        }

        return $mock;
    }

    protected function createCallableMock()
    {
        return $this
            ->getMockBuilder('React\Tests\Http\CallableStub')
            ->getMock();
    }
}
