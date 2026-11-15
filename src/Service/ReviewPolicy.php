<?php

namespace App\Service;

use App\Entity\Trip;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Enum\TripStatus;

/**
 * Un avis se dépose uniquement sur un trajet TERMINÉ, entre le conducteur et un passager ayant
 * effectivement voyagé (réservation terminée). Le passager note le conducteur, le conducteur
 * note chacun de ses passagers ; les passagers ne se notent pas entre eux.
 */
final class ReviewPolicy
{
    public function canReview(User $author, User $target, Trip $trip): bool
    {
        if (TripStatus::COMPLETED !== $trip->getStatus() || $author->sameAs($target)) {
            return false;
        }
        $driver = $trip->getDriver();
        if ($author->sameAs($driver)) {
            return $this->travelled($trip, $target);
        }

        return $target->sameAs($driver) && $this->travelled($trip, $author);
    }

    private function travelled(Trip $trip, User $user): bool
    {
        foreach ($trip->getBookings() as $booking) {
            if ($booking->getPassenger()->sameAs($user) && BookingStatus::COMPLETED === $booking->getStatus()) {
                return true;
            }
        }

        return false;
    }
}
