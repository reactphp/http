<?php

namespace React\Tests\Http\Io;

use PHPUnit\Framework\TestCase;
use React\Http\Io\Clock;

class ClockTest extends TestCase
{
    public function testNowReturnsSameTimestampMultipleTimesInSameTick()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $clock = new Clock($loop);

        $now = $clock->now();
        $this->assertTrue(is_float($now)); // assertIsFloat() on PHPUnit 8+
        $this->assertEquals($now, $clock->now());
    }

    public function testNowResetsMemoizedTimestampOnFutureTick()
    {
        $tick = null;
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('futureTick')->with($this->callback(function ($cb) use (&$tick) {
            $tick = $cb;
            return true;
        }));

        $clock = new Clock($loop);

        $now = $clock->now();

        $ref = new \ReflectionProperty($clock, 'now');
        $ref->setAccessible(true);
        $this->assertEquals($now, $ref->getValue($clock));

        $this->assertNotNull($tick);
        $tick();

        $this->assertNull($ref->getValue($clock));
    }
}
