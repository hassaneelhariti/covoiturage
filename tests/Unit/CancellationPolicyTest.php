<?php

namespace App\Tests\Unit;

use App\Entity\Booking;
use App\Enum\Actor;
use App\Service\CancellationPolicy;
use App\Tests\Support\TripBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CancellationPolicyTest extends TestCase
{
    private function booking(\DateTimeImmutable $departure): Booking
    {
        $trip = TripBuilder::trip([10], 3, $departure);

        return new Booking('CV-T', $trip, TripBuilder::user('Pax'), $trip->getStop(0), $trip->getStop(1), 1, 10, new \DateTimeImmutable());
    }

    #[DataProvider('ratios')]
    public function testPassengerRefundRatio(string $departureIn, float $expected): void
    {
        $now = new \DateTimeImmutable('2026-12-01 10:00');
        $this->assertSame($expected, (new CancellationPolicy())->refundRatio($now->modify($departureIn), $now));
    }

    public static function ratios(): iterable
    {
        yield '3 jours' => ['+3 days', 1.0];
        yield 'exactement 24 h' => ['+24 hours', 1.0];
        yield '23 h' => ['+23 hours', 0.5];
        yield 'exactement 2 h' => ['+2 hours', 0.5];
        yield '1 h 59' => ['+119 minutes', 0.0];
        yield 'déjà parti' => ['-1 hour', 0.0];
    }

    public function testPassengerCannotCancelAfterDeparture(): void
    {
        $now = new \DateTimeImmutable();
        $policy = new CancellationPolicy();
        $future = $this->booking($now->modify('+5 hours'));
        $past = $this->booking($now->modify('-1 hour'));
        $this->assertTrue($policy->canCancel($future, Actor::PASSENGER, $now));
        $this->assertFalse($policy->canCancel($past, Actor::PASSENGER, $now));
    }

    public function testCancelledBookingCannotBeCancelledAgain(): void
    {
        $now = new \DateTimeImmutable();
        $b = $this->booking($now->modify('+5 hours'));
        $b->cancel(Actor::PASSENGER, $now);
        $this->assertFalse((new CancellationPolicy())->canCancel($b, Actor::PASSENGER, $now));
    }

    public function testDriverCanCancelOnlyBeforeDeparture(): void
    {
        $now = new \DateTimeImmutable();
        $policy = new CancellationPolicy();
        $this->assertTrue($policy->canDriverCancel(TripBuilder::trip([10], 3, $now->modify('+1 hour')), $now));
        $this->assertFalse($policy->canDriverCancel(TripBuilder::trip([10], 3, $now->modify('-1 hour')), $now));
    }
}
