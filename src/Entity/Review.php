<?php

namespace App\Entity;

use App\Enum\ReviewRole;
use App\Repository\ReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** `role` = le rôle tenu par la personne notée (target) sur ce trajet. */
#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_review_trip_author_target', columns: ['trip_id', 'author_id', 'target_id'])]
class Review
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private Trip $trip,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private User $author,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] private User $target,
        #[ORM\Column(length: 20, enumType: ReviewRole::class)] private ReviewRole $role,
        #[ORM\Column] private int $rating,
        #[ORM\Column(type: Types::TEXT, nullable: true)] private ?string $comment,
        #[ORM\Column] private \DateTimeImmutable $createdAt,
    ) {
    }

    public function getId(): ?int { return $this->id; }
    public function getTrip(): Trip { return $this->trip; }
    public function getAuthor(): User { return $this->author; }
    public function getTarget(): User { return $this->target; }
    public function getRole(): ReviewRole { return $this->role; }
    public function getRoleLabel(): string { return $this->role->value; }
    public function getRating(): int { return $this->rating; }
    public function getComment(): ?string { return $this->comment; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
