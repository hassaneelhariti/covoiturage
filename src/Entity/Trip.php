<?php

namespace App\Entity;

use App\Enum\TripStatus;
use App\Exception\RuleViolation;
use App\Repository\TripRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TripRepository::class)]
class Trip
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: TripStatus::class)]
    private TripStatus $status = TripStatus::DRAFT;

    /** @var array<string, mixed>|null GeoJSON LineString renvoyé par OSRM */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $routeGeometry = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    /** @var Collection<int, TripStop> */
    #[ORM\OneToMany(mappedBy: 'trip', targetEntity: TripStop::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $stops;

    /** @var Collection<int, TripLeg> */
    #[ORM\OneToMany(mappedBy: 'trip', targetEntity: TripLeg::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $legs;

    /** @var Collection<int, Booking> */
    #[ORM\OneToMany(mappedBy: 'trip', targetEntity: Booking::class)]
    private Collection $bookings;

    public function __construct(
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private User $driver,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private Vehicle $vehicle,
        #[ORM\Column] private int $seatsTotal,
        #[ORM\Column(type: 'decimal', precision: 5, scale: 2)] private string $ratePerKm,
    ) {
        $this->stops = new ArrayCollection();
        $this->legs = new ArrayCollection();
        $this->bookings = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getDriver(): User { return $this->driver; }
    public function getVehicle(): Vehicle { return $this->vehicle; }
    public function getStatus(): TripStatus { return $this->status; }
    public function getStatusLabel(): string { return $this->status->value; }
    public function getSeatsTotal(): int { return $this->seatsTotal; }
    public function getRatePerKm(): string { return $this->ratePerKm; }
    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): void { $this->note = $note; }
    /** @return array<string, mixed>|null */
    public function getRouteGeometry(): ?array { return $this->routeGeometry; }
    /** @param array<string, mixed>|null $geometry */
    public function setRouteGeometry(?array $geometry): void { $this->routeGeometry = $geometry; }
    /** @return Collection<int, TripStop> */
    public function getStops(): Collection { return $this->stops; }
    /** @return Collection<int, TripLeg> */
    public function getLegs(): Collection { return $this->legs; }
    /** @return Collection<int, Booking> */
    public function getBookings(): Collection { return $this->bookings; }

    public function addStop(City $city, \DateTimeImmutable $arrivalAt, \DateTimeImmutable $departureAt, ?string $meetingPoint = null): TripStop
    {
        $stop = new TripStop($this, $city, $this->stops->count(), $arrivalAt, $departureAt, $meetingPoint);
        $this->stops->add($stop);

        return $stop;
    }

    public function addLeg(TripStop $from, TripStop $to, string $distanceKm, int $priceMad): TripLeg
    {
        $leg = new TripLeg($this, $from, $to, $from->getPosition(), $distanceKm, $priceMad, $this->seatsTotal);
        $this->legs->add($leg);

        return $leg;
    }

    public function getStop(int $position): ?TripStop
    {
        foreach ($this->stops as $stop) {
            if ($stop->getPosition() === $position) {
                return $stop;
            }
        }

        return null;
    }

    public function publish(): void
    {
        if (TripStatus::DRAFT !== $this->status) {
            throw new RuleViolation('Seul un brouillon peut être publié.');
        }
        if ($this->stops->count() < 2) {
            throw new RuleViolation('Un trajet doit avoir au moins deux arrêts.');
        }
        $this->status = TripStatus::PUBLISHED;
    }

    public function cancel(): void
    {
        if (TripStatus::PUBLISHED !== $this->status) {
            throw new RuleViolation('Seul un trajet publié peut être annulé.');
        }
        $this->status = TripStatus::CANCELLED;
    }

    public function complete(): void
    {
        if (TripStatus::PUBLISHED !== $this->status) {
            throw new RuleViolation('Seul un trajet publié peut être terminé.');
        }
        $this->status = TripStatus::COMPLETED;
    }

    public function departureAt(): \DateTimeImmutable
    {
        return $this->stops->first()->getDepartureAt();
    }

    public function arrivalAt(): \DateTimeImmutable
    {
        return $this->stops->last()->getArrivalAt();
    }

    /** Places libres pour un passager qui monte à $fromPosition et descend à $toPosition. */
    public function availableSeats(int $fromPosition, int $toPosition): int
    {
        $min = null;
        foreach ($this->legs as $leg) {
            if ($leg->getPosition() >= $fromPosition && $leg->getPosition() < $toPosition) {
                $min = null === $min ? $leg->remaining() : min($min, $leg->remaining());
            }
        }

        return max(0, $min ?? 0);
    }

    public function __toString(): string
    {
        return sprintf('#%s %s → %s', $this->id ?? 'new', $this->stops->first()?->getCity() ?? '?', $this->stops->last()?->getCity() ?? '?');
    }
}
