<?php

namespace React\Tests\Http\Io;

use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\Http\Io\Clock;

class ClockTest extends TestCase
{
    public function testNowReturnsSameTimestampMultipleTimesInSameTick()
    {
        $loop = $this->createMock(LoopInterface::class);

        $clock = new Clock($loop);

        $now = $clock->now();
        $this->assertTrue(is_float($now)); // assertIsFloat() on PHPUnit 8+
        $this->assertEquals($now, $clock->now());
    }

    public function testNowResetsMemoizedTimestampOnFutureTick()
    {
        $tick = null;
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())->method('futureTick')->with($this->callback(function ($cb) use (&$tick) {
            $tick = $cb;
            return true;
        }));

        $clock = new Clock($loop);

        $now = $clock->now();

        $ref = new \ReflectionProperty($clock, 'now');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $this->assertEquals($now, $ref->getValue($clock));

        $this->assertNotNull($tick);
        $tick();

        $this->assertNull($ref->getValue($clock));
    }
}
