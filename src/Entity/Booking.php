<?php

namespace App\Entity;

use App\Enum\Actor;
use App\Enum\BookingStatus;
use App\Exception\RuleViolation;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_booking_reference', columns: ['reference'])]
class Booking
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, enumType: BookingStatus::class)]
    private BookingStatus $status = BookingStatus::CONFIRMED;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\OneToOne(mappedBy: 'booking', cascade: ['persist'])]
    private Payment $payment;

    public function __construct(
        #[ORM\Column(length: 20)] private string $reference,
        #[ORM\ManyToOne(inversedBy: 'bookings'), ORM\JoinColumn(nullable: false)] private Trip $trip,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private User $passenger,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private TripStop $boardingStop,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private TripStop $alightingStop,
        #[ORM\Column] private int $seats,
        #[ORM\Column] private int $totalPriceMad,
        #[ORM\Column] private \DateTimeImmutable $createdAt,
    ) {
        $this->payment = new Payment($this, $totalPriceMad);
        $trip->getBookings()->add($this);
    }

    public function getId(): ?int { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function getTrip(): Trip { return $this->trip; }
    public function getPassenger(): User { return $this->passenger; }
    public function getBoardingStop(): TripStop { return $this->boardingStop; }
    public function getAlightingStop(): TripStop { return $this->alightingStop; }
    public function getSeats(): int { return $this->seats; }
    public function getTotalPriceMad(): int { return $this->totalPriceMad; }
    public function getStatus(): BookingStatus { return $this->status; }
    public function getStatusLabel(): string { return $this->status->value; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getCancelledAt(): ?\DateTimeImmutable { return $this->cancelledAt; }
    public function getPayment(): Payment { return $this->payment; }

    public function cancel(Actor $by, \DateTimeImmutable $at): void
    {
        if (BookingStatus::CONFIRMED !== $this->status) {
            throw new RuleViolation('Cette réservation ne peut plus être annulée.');
        }
        $this->status = Actor::DRIVER === $by ? BookingStatus::CANCELLED_BY_DRIVER : BookingStatus::CANCELLED_BY_PASSENGER;
        $this->cancelledAt = $at;
    }

    public function complete(): void
    {
        if (BookingStatus::CONFIRMED !== $this->status) {
            throw new RuleViolation('Seule une réservation confirmée peut être terminée.');
        }
        $this->status = BookingStatus::COMPLETED;
    }

    public function __toString(): string { return $this->reference; }
}
