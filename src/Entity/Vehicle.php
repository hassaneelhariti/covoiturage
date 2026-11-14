<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_vehicle_plate', columns: ['plate'])]
class Vehicle
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'vehicles'), ORM\JoinColumn(nullable: false)] private User $owner,
        #[ORM\Column(length: 60)] private string $make,
        #[ORM\Column(length: 60)] private string $model,
        #[ORM\Column(length: 40)] private string $color,
        #[ORM\Column(length: 20)] private string $plate,
        #[ORM\Column] private int $seats,
    ) {
        $owner->getVehicles()->add($this);
    }

    public function getId(): ?int { return $this->id; }
    public function getOwner(): User { return $this->owner; }
    public function getMake(): string { return $this->make; }
    public function getModel(): string { return $this->model; }
    public function getColor(): string { return $this->color; }
    public function getPlate(): string { return $this->plate; }
    /** Places totales, conducteur compris. */
    public function getSeats(): int { return $this->seats; }
    public function __toString(): string { return sprintf('%s %s (%s)', $this->make, $this->model, $this->plate); }
}
