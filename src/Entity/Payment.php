<?php

namespace App\Entity;

use App\Enum\PaymentStatus;
use Doctrine\ORM\Mapping as ORM;

/** Paiement simulé : aucune intégration réelle, seuls les montants sont tracés. */
#[ORM\Entity]
class Payment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $refundedMad = 0;

    #[ORM\Column(length: 30, enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::PAID;

    public function __construct(
        #[ORM\OneToOne(inversedBy: 'payment'), ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Booking $booking,
        #[ORM\Column] private int $amountMad,
    ) {
    }

    public function getId(): ?int { return $this->id; }
    public function getBooking(): Booking { return $this->booking; }
    public function getAmountMad(): int { return $this->amountMad; }
    public function getRefundedMad(): int { return $this->refundedMad; }
    public function getStatus(): PaymentStatus { return $this->status; }

    /** @param float $ratio part remboursée, entre 0 et 1 */
    public function refund(float $ratio): void
    {
        $ratio = max(0.0, min(1.0, $ratio));
        $this->refundedMad = (int) round($this->amountMad * $ratio);
        $this->status = match (true) {
            0 === $this->refundedMad => PaymentStatus::PAID,
            $this->refundedMad >= $this->amountMad => PaymentStatus::REFUNDED,
            default => PaymentStatus::PARTIALLY_REFUNDED,
        };
    }
}
