<?php

namespace App\Entity;

use App\Exception\RuleViolation;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tronçon entre deux arrêts consécutifs. La disponibilité se gère ici : une réservation
 * « de B à D » occupe les tronçons B→C et C→D, et seulement ceux-là.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_leg_trip_position', columns: ['trip_id', 'position'])]
class TripLeg
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $seatsTaken = 0;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'legs'), ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Trip $trip,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private TripStop $fromStop,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private TripStop $toStop,
        #[ORM\Column] private int $position,
        #[ORM\Column(type: 'decimal', precision: 7, scale: 2)] private string $distanceKm,
        #[ORM\Column] private int $priceMad,
        #[ORM\Column] private int $capacity,
    ) {
    }

    public function getId(): ?int { return $this->id; }
    public function getTrip(): Trip { return $this->trip; }
    public function getFromStop(): TripStop { return $this->fromStop; }
    public function getToStop(): TripStop { return $this->toStop; }
    public function getPosition(): int { return $this->position; }
    public function getDistanceKm(): string { return $this->distanceKm; }
    public function getPriceMad(): int { return $this->priceMad; }
    public function getCapacity(): int { return $this->capacity; }
    public function getSeatsTaken(): int { return $this->seatsTaken; }

    public function remaining(): int
    {
        return $this->capacity - $this->seatsTaken;
    }

    public function take(int $n): void
    {
        if ($n < 1) {
            throw new \InvalidArgumentException('Le nombre de places doit être positif.');
        }
        if ($n > $this->remaining()) {
            throw new RuleViolation('Plus assez de places disponibles sur ce tronçon.');
        }
        $this->seatsTaken += $n;
    }

    public function release(int $n): void
    {
        if ($n < 1 || $n > $this->seatsTaken) {
            throw new \LogicException('Libération de places incohérente.');
        }
        $this->seatsTaken -= $n;
    }
}
