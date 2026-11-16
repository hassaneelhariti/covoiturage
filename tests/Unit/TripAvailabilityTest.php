<?php

namespace App\Tests\Unit;

use App\Tests\Support\TripBuilder;
use PHPUnit\Framework\TestCase;

/** Trajet A(0) → B(1) → C(2) → D(3), 3 places. */
class TripAvailabilityTest extends TestCase
{
    public function testSeatsAreCountedPerSegment(): void
    {
        $trip = TripBuilder::trip([10, 10, 10]);
        [$ab, $bc, $cd] = $trip->getLegs()->toArray();
        $ab->take(2);          // passager 1 : A→B
        $bc->take(1); $cd->take(1); // passager 2 : B→D

        $this->assertSame(1, $trip->availableSeats(0, 1));
        $this->assertSame(2, $trip->availableSeats(1, 2));
        $this->assertSame(1, $trip->availableSeats(0, 3), 'le plus contraint des tronçons décide');
        $this->assertSame(2, $trip->availableSeats(2, 3));
    }

    public function testFreedSeatAtIntermediateStopCanBeResold(): void
    {
        $trip = TripBuilder::trip([10, 10]);
        [$ab, $bc] = $trip->getLegs()->toArray();
        $ab->take(3); // trois passagers descendent à B
        $this->assertSame(0, $trip->availableSeats(0, 2));
        $this->assertSame(3, $trip->availableSeats(1, 2), 'les 3 places sont revendables de B à C');
    }

    public function testInvalidRangeHasNoSeats(): void
    {
        $this->assertSame(0, TripBuilder::trip()->availableSeats(2, 2));
    }
}
