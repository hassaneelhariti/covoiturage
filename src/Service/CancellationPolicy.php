<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Enum\Actor;
use App\Enum\BookingStatus;
use App\Enum\TripStatus;

/**
 * Passager : remboursement 100 % si le départ de SON arrêt de montée est à ≥ 24 h,
 * 50 % entre 2 h et 24 h, 0 % en dessous de 2 h. Annulation impossible après le départ.
 * Conducteur : peut annuler tant que le trajet n'est pas parti ; tous les passagers sont
 * remboursés à 100 % et le compteur d'annulations du conducteur augmente.
 */
final class CancellationPolicy
{
    public const FULL_REFUND_HOURS = 24;
    public const HALF_REFUND_HOURS = 2;

    public function refundRatio(\DateTimeImmutable $departureAt, \DateTimeImmutable $at): float
    {
        $hours = ($departureAt->getTimestamp() - $at->getTimestamp()) / 3600;

        return match (true) {
            $hours >= self::FULL_REFUND_HOURS => 1.0,
            $hours >= self::HALF_REFUND_HOURS => 0.5,
            default => 0.0,
        };
    }

    public function canCancel(Booking $booking, Actor $actor, \DateTimeImmutable $at): bool
    {
        if (BookingStatus::CONFIRMED !== $booking->getStatus()) {
            return false;
        }
        if (Actor::DRIVER === $actor) {
            return $this->canDriverCancel($booking->getTrip(), $at);
        }

        return $at < $booking->getBoardingStop()->getDepartureAt();
    }

    public function canDriverCancel(Trip $trip, \DateTimeImmutable $at): bool
    {
        return TripStatus::PUBLISHED === $trip->getStatus() && $at < $trip->departureAt();
    }
}
