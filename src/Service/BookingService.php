<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\TripStop;
use App\Entity\User;
use App\Enum\Actor;
use App\Enum\BookingStatus;
use App\Enum\TripStatus;
use App\Exception\RuleViolation;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class BookingService
{
    public const MAX_SEATS_PER_BOOKING = 4;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TripLocker $locker,
        private readonly CancellationPolicy $policy,
        private readonly PricingService $pricing,
        private readonly ClockInterface $clock,
    ) {
    }

    public function book(Trip $trip, User $passenger, TripStop $from, TripStop $to, int $seats): Booking
    {
        if ($seats < 1 || $seats > self::MAX_SEATS_PER_BOOKING) {
            throw new RuleViolation(sprintf('Vous pouvez réserver de 1 à %d places à la fois.', self::MAX_SEATS_PER_BOOKING));
        }
        if ($from->getTrip() !== $trip || $to->getTrip() !== $trip || $from->getPosition() >= $to->getPosition()) {
            throw new RuleViolation('Portion de trajet invalide : la montée doit précéder la descente.');
        }

        return $this->locker->transactional(function () use ($trip, $passenger, $from, $to, $seats): Booking {
            $this->locker->lock($trip);

            if (TripStatus::PUBLISHED !== $trip->getStatus()) {
                throw new RuleViolation("Ce trajet n'est plus ouvert à la réservation.");
            }
            if ($passenger->sameAs($trip->getDriver())) {
                throw new RuleViolation('Vous ne pouvez pas réserver votre propre trajet.');
            }
            $now = $this->clock->now();
            if ($now >= $from->getDepartureAt()) {
                throw new RuleViolation('Le départ de cet arrêt est déjà passé.');
            }
            $already = $this->em->getRepository(Booking::class)->count([
                'trip' => $trip, 'passenger' => $passenger, 'status' => BookingStatus::CONFIRMED,
            ]);
            if ($already > 0) {
                throw new RuleViolation('Vous avez déjà une réservation active sur ce trajet.');
            }

            $legs = $this->locker->legs($trip, $from->getPosition(), $to->getPosition());
            foreach ($legs as $leg) {
                if ($leg->remaining() < $seats) {
                    throw new RuleViolation('Plus assez de places disponibles sur cette portion.');
                }
            }
            foreach ($legs as $leg) {
                $leg->take($seats);
            }

            $unit = array_sum(array_map(fn ($l) => $l->getPriceMad(), $legs));
            $booking = new Booking(
                'CV-'.strtoupper(bin2hex(random_bytes(4))),
                $trip, $passenger, $from, $to, $seats, $unit * $seats, \DateTimeImmutable::createFromInterface($now),
            );
            $this->em->persist($booking); // cascade → Payment (simulé, statut PAID)

            return $booking;
        });
    }

    public function cancelByPassenger(Booking $booking, User $actor): void
    {
        if (!$booking->getPassenger()->sameAs($actor)) {
            throw new RuleViolation('Seul le passager peut annuler sa réservation.');
        }
        $this->locker->transactional(function () use ($booking): void {
            $this->locker->lock($booking->getTrip());
            $this->em->refresh($booking);
            $now = \DateTimeImmutable::createFromInterface($this->clock->now());
            if (!$this->policy->canCancel($booking, Actor::PASSENGER, $now)) {
                throw new RuleViolation('Cette réservation ne peut plus être annulée (déjà annulée ou départ passé).');
            }
            foreach ($this->locker->legs($booking->getTrip(), $booking->getBoardingStop()->getPosition(), $booking->getAlightingStop()->getPosition()) as $leg) {
                $leg->release($booking->getSeats());
            }
            $booking->cancel(Actor::PASSENGER, $now);
            $booking->getPayment()->refund($this->policy->refundRatio($booking->getBoardingStop()->getDepartureAt(), $now));
        });
    }

    public function cancelByDriver(Trip $trip, User $actor): void
    {
        if (!$trip->getDriver()->sameAs($actor)) {
            throw new RuleViolation('Seul le conducteur peut annuler ce trajet.');
        }
        $this->locker->transactional(function () use ($trip): void {
            $this->locker->lock($trip);
            $now = \DateTimeImmutable::createFromInterface($this->clock->now());
            if (!$this->policy->canDriverCancel($trip, $now)) {
                throw new RuleViolation('Ce trajet ne peut plus être annulé (déjà annulé, terminé ou parti).');
            }
            $this->locker->legs($trip, 0, PHP_INT_MAX);
            foreach ($trip->getBookings() as $booking) {
                if (BookingStatus::CONFIRMED !== $booking->getStatus()) {
                    continue;
                }
                foreach ($trip->getLegs() as $leg) {
                    if ($leg->getPosition() >= $booking->getBoardingStop()->getPosition() && $leg->getPosition() < $booking->getAlightingStop()->getPosition()) {
                        $leg->release($booking->getSeats());
                    }
                }
                $booking->cancel(Actor::DRIVER, $now);
                $booking->getPayment()->refund(1.0); // le conducteur annule : remboursement intégral
            }
            $trip->cancel();
            $trip->getDriver()->incrementDriverCancellations();
        });
    }
}
