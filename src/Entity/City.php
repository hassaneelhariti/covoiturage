<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_city_name', columns: ['name'])]
class City
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 100)] private string $name,
        #[ORM\Column(length: 100)] private string $region,
        #[ORM\Column(type: 'decimal', precision: 9, scale: 6)] private string $latitude,
        #[ORM\Column(type: 'decimal', precision: 9, scale: 6)] private string $longitude,
    ) {
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getRegion(): string { return $this->region; }
    public function getLatitude(): string { return $this->latitude; }
    public function getLongitude(): string { return $this->longitude; }
    public function setName(string $name): void { $this->name = $name; }
    public function setRegion(string $region): void { $this->region = $region; }
    public function setLatitude(string $latitude): void { $this->latitude = $latitude; }
    public function setLongitude(string $longitude): void { $this->longitude = $longitude; }
    public function __toString(): string { return $this->name; }
}
