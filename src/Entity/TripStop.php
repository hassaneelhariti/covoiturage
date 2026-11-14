<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_stop_trip_position', columns: ['trip_id', 'position'])]
class TripStop
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'stops'), ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Trip $trip,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private City $city,
        #[ORM\Column] private int $position,
        #[ORM\Column] private \DateTimeImmutable $arrivalAt,
        #[ORM\Column] private \DateTimeImmutable $departureAt,
        #[ORM\Column(length: 150, nullable: true)] private ?string $meetingPoint = null,
    ) {
    }

    public function getId(): ?int { return $this->id; }
    public function getTrip(): Trip { return $this->trip; }
    public function getCity(): City { return $this->city; }
    public function getPosition(): int { return $this->position; }
    public function getArrivalAt(): \DateTimeImmutable { return $this->arrivalAt; }
    public function getDepartureAt(): \DateTimeImmutable { return $this->departureAt; }
    public function getMeetingPoint(): ?string { return $this->meetingPoint; }
    public function __toString(): string { return $this->city->getName().' #'.$this->position; }
}
