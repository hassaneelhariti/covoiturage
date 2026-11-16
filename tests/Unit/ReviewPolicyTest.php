<?php

namespace App\Tests\Unit;

use App\Entity\Booking;
use App\Service\ReviewPolicy;
use App\Tests\Support\TripBuilder;
use PHPUnit\Framework\TestCase;

class ReviewPolicyTest extends TestCase
{
    private function scenario(bool $complete): array
    {
        $trip = TripBuilder::trip();
        $pax = TripBuilder::user('Pax');
        $other = TripBuilder::user('Other');
        $booking = new Booking('CV-R', $trip, $pax, $trip->getStop(0), $trip->getStop(1), 1, 10, new \DateTimeImmutable());
        if ($complete) {
            $trip->complete();
            $booking->complete();
        }

        return [$trip, $pax, $other];
    }

    public function testNoReviewBeforeTripIsCompleted(): void
    {
        [$trip, $pax] = $this->scenario(false);
        $this->assertFalse((new ReviewPolicy())->canReview($pax, $trip->getDriver(), $trip));
        $this->assertFalse((new ReviewPolicy())->canReview($trip->getDriver(), $pax, $trip));
    }

    public function testPassengerAndDriverCanReviewEachOtherAfterCompletion(): void
    {
        [$trip, $pax] = $this->scenario(true);
        $this->assertTrue((new ReviewPolicy())->canReview($pax, $trip->getDriver(), $trip));
        $this->assertTrue((new ReviewPolicy())->canReview($trip->getDriver(), $pax, $trip));
    }

    public function testStrangersAndSelfReviewAreRefused(): void
    {
        [$trip, $pax, $other] = $this->scenario(true);
        $policy = new ReviewPolicy();
        $this->assertFalse($policy->canReview($other, $trip->getDriver(), $trip), 'non-passager');
        $this->assertFalse($policy->canReview($trip->getDriver(), $other, $trip), 'non-passager');
        $this->assertFalse($policy->canReview($pax, $pax, $trip), 'auto-avis');
    }

    public function testPassengersDoNotReviewEachOther(): void
    {
        [$trip, $pax] = $this->scenario(false);
        $pax2 = TripBuilder::user('Pax2');
        $b2 = new Booking('CV-R2', $trip, $pax2, $trip->getStop(0), $trip->getStop(1), 1, 10, new \DateTimeImmutable());
        $trip->complete();
        foreach ($trip->getBookings() as $b) {
            $b->complete();
        }
        $this->assertFalse((new ReviewPolicy())->canReview($pax, $pax2, $trip));
    }
}
