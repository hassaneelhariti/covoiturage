<?php

namespace App\Service;

use App\Entity\Review;
use App\Entity\Trip;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Enum\ReviewRole;
use App\Exception\RuleViolation;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class ReviewService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReviewPolicy $policy,
        private readonly ClockInterface $clock,
    ) {
    }

    public function submit(User $author, Trip $trip, User $target, int $rating, ?string $comment): Review
    {
        if ($rating < 1 || $rating > 5) {
            throw new RuleViolation('La note doit être comprise entre 1 et 5.');
        }
        if (!$this->policy->canReview($author, $target, $trip)) {
            throw new RuleViolation("Vous ne pouvez noter cette personne qu'après un trajet effectué ensemble.");
        }
        if ($this->em->getRepository(Review::class)->count(['trip' => $trip, 'author' => $author, 'target' => $target]) > 0) {
            throw new RuleViolation('Vous avez déjà laissé un avis pour cette personne sur ce trajet.');
        }
        $role = $target->sameAs($trip->getDriver()) ? ReviewRole::DRIVER : ReviewRole::PASSENGER;
        $comment = null !== $comment ? trim($comment) : null;
        $review = new Review($trip, $author, $target, $role, $rating, '' === $comment ? null : $comment, \DateTimeImmutable::createFromInterface($this->clock->now()));
        $this->em->persist($review);
        $this->em->flush();

        return $review;
    }

    /** @return list<User> personnes que $user peut encore noter sur ce trajet */
    public function pendingTargets(User $user, Trip $trip): array
    {
        $candidates = [];
        if ($user->sameAs($trip->getDriver())) {
            foreach ($trip->getBookings() as $b) {
                if (BookingStatus::COMPLETED === $b->getStatus()) {
                    $candidates[$b->getPassenger()->getId()] = $b->getPassenger();
                }
            }
        } else {
            $candidates[$trip->getDriver()->getId()] = $trip->getDriver();
        }
        $pending = [];
        foreach ($candidates as $target) {
            $done = $this->em->getRepository(Review::class)->count(['trip' => $trip, 'author' => $user, 'target' => $target]);
            if (0 === $done && $this->policy->canReview($user, $target, $trip)) {
                $pending[] = $target;
            }
        }

        return $pending;
    }
}
