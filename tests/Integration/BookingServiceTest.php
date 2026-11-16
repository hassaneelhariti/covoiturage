<?php

namespace App\Tests\Integration;

use App\Entity\Booking;
use App\Entity\TripLeg;
use App\Enum\BookingStatus;
use App\Enum\PaymentStatus;
use App\Enum\TripStatus;
use App\Exception\RuleViolation;
use App\Service\BookingService;
use App\Tests\Support\TripBuilder;

class BookingServiceTest extends IntegrationTestCase
{
    private BookingService $bookings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookings = $this->service(BookingService::class);
    }

    private function pax(string $name = 'Pax'): \App\Entity\User
    {
        return $this->persistUser(TripBuilder::user($name));
    }

    private function seatsTaken(\App\Entity\Trip $trip): array
    {
        $this->em->clear();
        $rows = $this->em->getRepository(TripLeg::class)->findBy(['trip' => $trip->getId()], ['position' => 'ASC']);

        return array_map(fn (TripLeg $l) => $l->getSeatsTaken(), $rows);
    }

    public function testSeatsStayCorrectWhenPassengersBookDifferentPortions(): void
    {
        $trip = $this->persistTrip(TripBuilder::trip([10, 10], 3)); // A → B → C, 3 places
        [$a, $b, $c] = $trip->getStops()->toArray();

        $this->bookings->book($trip, $this->pax(), $a, $b, 2);
        $this->bookings->book($trip, $this->pax(), $b, $c, 3); // B→C est encore entièrement libre
        $this->assertSame(1, $trip->availableSeats(0, 1));
        $this->assertSame(0, $trip->availableSeats(1, 2));

        try {
            $this->bookings->book($trip, $this->pax(), $a, $c, 2);
            $this->fail('A→C à 2 places doit être refusé (A→B n\'a plus qu\'une place).');
        } catch (RuleViolation) {
        }
        $this->bookings->book($trip, $this->pax(), $a, $b, 1); // la dernière place de A→B
        $this->assertSame([3, 3], $this->seatsTaken($trip));
    }

    public function testBookingPriceIsSumOfLegsTimesSeats(): void
    {
        $trip = $this->persistTrip(TripBuilder::trip([10, 20], 3));
        [$a, , $c] = $trip->getStops()->toArray();
        $booking = $this->bookings->book($trip, $this->pax(), $a, $c, 2);
        $this->assertSame((10 + 20) * 2, $booking->getTotalPriceMad());
        $this->assertSame(PaymentStatus::PAID, $booking->getPayment()->getStatus());
        $this->assertSame($booking->getTotalPriceMad(), $booking->getPayment()->getAmountMad());
    }

    public function testNoOverbookingOnSameSegment(): void
    {
        $trip = $this->persistTrip(TripBuilder::trip([10], 3));
        [$a, $b] = $trip->getStops()->toArray();
        $this->bookings->book($trip, $this->pax(), $a, $b, 2);
        $this->expectException(RuleViolation::class);
        $this->bookings->book($trip, $this->pax(), $a, $b, 2);
    }

    /** Simule un autre processus ayant pris les places entre la lecture et la réservation. */
    public function testStaleInMemoryStateCannotCauseOverbooking(): void
    {
        $trip = $this->persistTrip(TripBuilder::trip([10], 3));
        [$a, $b] = $trip->getStops()->toArray();
        $this->assertSame(3, $trip->availableSeats(0, 1)); // état « lu » : 3 places libres
        $this->em->getConnection()->executeStatement('UPDATE trip_leg SET seats_taken = 3 WHERE trip_id = ?', [$trip->getId()]);

        $this->expectException(RuleViolation::class);
        $this->bookings->book($trip, $this->pax(), $a, $b, 1);
    }

    public function testDatabaseCheckConstraintRejectsOverbookingEvenIfApplicationFails(): void
    {
        $trip = $this->persistTrip(TripBuilder::trip([10], 3));
        $this->expectException(\Doctrine\DBAL\Exception::class);
        $this->em->getConnection()->executeStatement('UPDATE trip_leg SET seats_taken = 4 WHERE trip_id = ?', [$trip->getId()]);
    }

    public function testDriverCannotBookOwnTripNorBookTwice(): void
    {
        $trip = $this->persistTrip(TripBuilder::trip([10], 3));
        [$a, $b] = $trip->getStops()->toArray();
        try {
            $this->bookings->book($trip, $trip->getDriver(), $a, $b, 1);
            $this->fail('Le conducteur ne peut pas réserver son propre trajet.');
        } catch (RuleViolation) {
        }
        $pax = $this->pax();
        $this->bookings->book($trip, $pax, $a, $b, 1);
        $this->expectException(RuleViolation::class);
        $this->bookings->book($trip, $pax, $a, $b, 1);
    }

    public function testInvalidSeatCountsAndPortionsAreRefused(): void
    {
        $trip = $this->persistTrip(TripBuilder::trip([10, 10], 3));
        [$a, $b, $c] = $trip->getStops()->toArray();
        foreach ([[$a, $b, 0], [$a, $b, 5], [$b, $a, 1], [$b, $b, 1]] as [$from, $to, $n]) {
            try {
                $this->bookings->book($trip, $this->pax(), $from, $to, $n);
                $this->fail('Réservation invalide acceptée.');
            } catch (RuleViolation) {
            }
        }
        $this->addToAssertionCount(1);
    }

    public function testPassengerCancellationFreesSeatsAndRefundsFullyFarFromDeparture(): void
    {
        $trip = $this->persistTrip(TripBuilder::trip([10, 10], 3, new \DateTimeImmutable('+3 days')));
        [$a, , $c] = $trip->getStops()->toArray();
        $pax = $this->pax();
        $booking = $this->bookings->book($trip, $pax, $a, $c, 2);

        $this->bookings->cancelByPassenger($booking, $pax);

        $this->assertSame(BookingStatus::CANCELLED_BY_PASSENGER, $booking->getStatus());
        $this->assertSame(PaymentStatus::REFUNDED, $booking->getPayment()->getStatus());
        $this->assertSame($booking->getTotalPriceMad(), $booking->getPayment()->getRefundedMad());
        $this->assertSame([0, 0], $this->seatsTaken($trip));
    }

    public function testPassengerGetsHalfRefundBetween2And24HoursAndNothingUnder2Hours(): void
    {
        foreach (['+10 hours' => PaymentStatus::PARTIALLY_REFUNDED, '+1 hour' => PaymentStatus::PAID] as $in => $expected) {
            $trip = $this->persistTrip(TripBuilder::trip([100], 3, new \DateTimeImmutable($in)));
            [$a, $b] = $trip->getStops()->toArray();
            $pax = $this->pax();
            $booking = $this->bookings->book($trip, $pax, $a, $b, 1);
            $this->bookings->cancelByPassenger($booking, $pax);
            $this->assertSame($expected, $booking->getPayment()->getStatus(), $in);
            if (PaymentStatus::PARTIALLY_REFUNDED === $expected) {
                $this->assertSame(intdiv($booking->getTotalPriceMad(), 2), $booking->getPayment()->getRefundedMad());
            } else {
                $this->assertSame(0, $booking->getPayment()->getRefundedMad());
            }
        }
    }

    public function testPassengerCannotCancelAfterDepartureNorSomeoneElsesBooking(): void
    {
        $trip = $this->persistTrip(TripBuilder::trip([10], 3, new \DateTimeImmutable('-1 hour')));
        [$a, $b] = $trip->getStops()->toArray();
        $pax = $this->pax();
        $booking = new Booking('CV-LATE', $trip, $pax, $a, $b, 1, 10, new \DateTimeImmutable('-2 days'));
        $trip->getLegs()->first()->take(1);
        $this->em->persist($booking);
        $this->em->flush();

        try {
            $this->bookings->cancelByPassenger($booking, $pax);
            $this->fail('Annulation après le départ acceptée.');
        } catch (RuleViolation) {
        }
        $this->expectException(RuleViolation::class);
        $this->bookings->cancelByPassenger($booking, $this->pax('Intrus'));
    }

    public function testDriverCancellationRefundsEveryoneInFullAndCountsAgainstDriver(): void
    {
        $trip = $this->persistTrip(TripBuilder::trip([10, 10], 3, new \DateTimeImmutable('+30 minutes')));
        [$a, $b, $c] = $trip->getStops()->toArray();
        $b1 = $this->bookings->book($trip, $this->pax(), $a, $b, 1);
        $b2 = $this->bookings->book($trip, $this->pax(), $a, $c, 2);

        $this->bookings->cancelByDriver($trip, $trip->getDriver());

        $this->assertSame(TripStatus::CANCELLED, $trip->getStatus());
        foreach ([$b1, $b2] as $b) {
            $this->assertSame(BookingStatus::CANCELLED_BY_DRIVER, $b->getStatus());
            $this->assertSame(PaymentStatus::REFUNDED, $b->getPayment()->getStatus(), 'remboursement intégral même à 30 min du départ');
            $this->assertSame($b->getTotalPriceMad(), $b->getPayment()->getRefundedMad());
        }
        $this->assertSame(1, $trip->getDriver()->getDriverCancellations());
        $this->assertSame([0, 0], $this->seatsTaken($trip));
    }

    public function testCancelledTripAcceptsNoMoreBookingsAndOnlyDriverCanCancel(): void
    {
        $trip = $this->persistTrip(TripBuilder::trip([10], 3));
        [$a, $b] = $trip->getStops()->toArray();
        try {
            $this->bookings->cancelByDriver($trip, $this->pax('Intrus'));
            $this->fail('Un tiers a annulé le trajet.');
        } catch (RuleViolation) {
        }
        $this->bookings->cancelByDriver($trip, $trip->getDriver());
        $this->expectException(RuleViolation::class);
        $this->bookings->book($trip, $this->pax(), $a, $b, 1);
    }
}
