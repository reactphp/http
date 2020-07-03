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
        if (method_exists('PHPUnit\Framework\MockObject\MockBuilder', 'addMethods')) {
            // PHPUnit 10+
            return $this->getMockBuilder('stdClass')->addMethods(array('__invoke'))->getMock();
        } else {
            // legacy PHPUnit 4 - PHPUnit 9
            return $this->getMockBuilder('stdClass')->setMethods(array('__invoke'))->getMock();
        }
    }

    public function assertContainsString($needle, $haystack)
    {
        if (method_exists($this, 'assertStringContainsString')) {
            // PHPUnit 7.5+
            $this->assertStringContainsString($needle, $haystack);
        } else {
            // legacy PHPUnit 4 - PHPUnit 7.5
            $this->assertContains($needle, $haystack);
        }
    }

    public function assertNotContainsString($needle, $haystack)
    {
        if (method_exists($this, 'assertStringNotContainsString')) {
            // PHPUnit 7.5+
            $this->assertStringNotContainsString($needle, $haystack);
        } else {
            // legacy PHPUnit 4 - PHPUnit 7.5
            $this->assertNotContains($needle, $haystack);
        }
    }

    public function setExpectedException($exception, $exceptionMessage = '', $exceptionCode = null)
    {
        if (method_exists($this, 'expectException')) {
            // PHPUnit 5+
            $this->expectException($exception);
            if ($exceptionMessage !== '') {
                $this->expectExceptionMessage($exceptionMessage);
            }
            if ($exceptionCode !== null) {
                $this->expectExceptionCode($exceptionCode);
            }
        } else {
            // legacy PHPUnit 4
            parent::setExpectedException($exception, $exceptionMessage, $exceptionCode);
        }
    }

}
