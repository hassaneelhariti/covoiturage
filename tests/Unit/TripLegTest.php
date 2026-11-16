<?php

namespace App\Tests\Unit;

use App\Exception\RuleViolation;
use App\Tests\Support\TripBuilder;
use PHPUnit\Framework\TestCase;

class TripLegTest extends TestCase
{
    public function testTakeAndReleaseKeepCountersConsistent(): void
    {
        $leg = TripBuilder::trip()->getLegs()->first();
        $this->assertSame(3, $leg->remaining());
        $leg->take(2);
        $this->assertSame(1, $leg->remaining());
        $leg->release(1);
        $this->assertSame(2, $leg->remaining());
    }

    public function testCannotTakeMoreThanRemaining(): void
    {
        $leg = TripBuilder::trip()->getLegs()->first();
        $leg->take(3);
        $this->expectException(RuleViolation::class);
        $leg->take(1);
    }

    public function testCannotReleaseMoreThanTaken(): void
    {
        $leg = TripBuilder::trip()->getLegs()->first();
        $leg->take(1);
        $this->expectException(\LogicException::class);
        $leg->release(2);
    }
}
