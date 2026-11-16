<?php

namespace App\Tests\Integration;

use App\Entity\Booking;
use App\Enum\BookingStatus;
use App\Enum\ReviewRole;
use App\Exception\RuleViolation;
use App\Repository\ReviewRepository;
use App\Service\ReviewService;
use App\Service\TripService;
use App\Tests\Support\TripBuilder;

class ReviewAndCompletionTest extends IntegrationTestCase
{
    /** Trajet parti il y a 2 jours (donc arrivé), un passager avec une réservation confirmée. */
    private function pastTripWithPassenger(): array
    {
        $trip = $this->persistTrip(TripBuilder::trip([10], 3, new \DateTimeImmutable('-2 days')));
        $pax = $this->persistUser(TripBuilder::user('Pax'));
        $booking = new Booking('CV-PAST', $trip, $pax, $trip->getStop(0), $trip->getStop(1), 1, 10, new \DateTimeImmutable('-5 days'));
        $trip->getLegs()->first()->take(1);
        $this->em->persist($booking);
        $this->em->flush();

        return [$trip, $pax, $booking];
    }

    public function testNoReviewBeforeTripIsCompleted(): void
    {
        [$trip, $pax] = $this->pastTripWithPassenger();
        $this->expectException(RuleViolation::class);
        $this->service(ReviewService::class)->submit($pax, $trip, $trip->getDriver(), 5, 'Top');
    }

    public function testTripCannotBeCompletedBeforeItsArrivalNorByAStranger(): void
    {
        $future = $this->persistTrip(TripBuilder::trip([10], 3, new \DateTimeImmutable('+2 days')));
        try {
            $this->service(TripService::class)->complete($future, $future->getDriver());
            $this->fail('Trajet terminé avant son arrivée.');
        } catch (RuleViolation) {
        }
        [$trip, $pax] = $this->pastTripWithPassenger();
        $this->expectException(RuleViolation::class);
        $this->service(TripService::class)->complete($trip, $pax);
    }

    public function testReviewsOpenAfterCompletionAndAreUniquePerPair(): void
    {
        [$trip, $pax, $booking] = $this->pastTripWithPassenger();
        $this->service(TripService::class)->complete($trip, $trip->getDriver());
        $this->assertSame(BookingStatus::COMPLETED, $booking->getStatus());

        $reviews = $this->service(ReviewService::class);
        $review = $reviews->submit($pax, $trip, $trip->getDriver(), 4, ' Bon trajet ');
        $this->assertSame(ReviewRole::DRIVER, $review->getRole());
        $this->assertSame('Bon trajet', $review->getComment());
        $reviews->submit($trip->getDriver(), $trip, $pax, 5, null);

        $summary = $this->service(ReviewRepository::class)->summary($trip->getDriver(), ReviewRole::DRIVER);
        $this->assertSame(['average' => 4.0, 'count' => 1], $summary);

        $this->expectException(RuleViolation::class); // doublon
        $reviews->submit($pax, $trip, $trip->getDriver(), 1, 'Bis');
    }

    public function testRatingMustBeBetweenOneAndFiveAndStrangersCannotReview(): void
    {
        [$trip, $pax] = $this->pastTripWithPassenger();
        $this->service(TripService::class)->complete($trip, $trip->getDriver());
        $reviews = $this->service(ReviewService::class);
        foreach ([0, 6] as $bad) {
            try {
                $reviews->submit($pax, $trip, $trip->getDriver(), $bad, null);
                $this->fail("Note $bad acceptée.");
            } catch (RuleViolation) {
            }
        }
        $stranger = $this->persistUser(TripBuilder::user('Stranger'));
        $this->expectException(RuleViolation::class);
        $reviews->submit($stranger, $trip, $trip->getDriver(), 5, null);
    }
}
