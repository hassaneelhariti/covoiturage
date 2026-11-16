<?php

namespace App\Tests\Unit;

use App\Service\PricingService;
use App\Tests\Support\TripBuilder;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    public function testLegPriceIsRoundedWithMinimumOfOneDirham(): void
    {
        $p = new PricingService();
        $this->assertSame(60, $p->legPrice(100.0, 0.60));
        $this->assertSame(1, $p->legPrice(0.4, 0.60));
        $this->assertSame(31, $p->legPrice(51.4, 0.60));
    }

    public function testPortionPricesSumToFullTripPrice(): void
    {
        $trip = TripBuilder::trip([10.4, 20.4, 30.4]);
        $p = new PricingService();
        $this->assertSame(
            $p->portionPrice($trip, 0, 3),
            $p->portionPrice($trip, 0, 1) + $p->portionPrice($trip, 1, 2) + $p->portionPrice($trip, 2, 3),
        );
        $this->assertSame(10 + 20 + 30, $p->portionPrice($trip, 0, 3));
    }

    public function testSuggestedTotal(): void
    {
        $this->assertSame(60 + 30, (new PricingService())->suggestedTotal([100.0, 50.0], 0.60));
    }
}
