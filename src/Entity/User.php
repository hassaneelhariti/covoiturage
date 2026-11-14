<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $password = '';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $driverCancellations = 0;

    /** @var Collection<int, Vehicle> */
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Vehicle::class)]
    private Collection $vehicles;

    public function __construct(
        #[ORM\Column(length: 180)] private string $email,
        #[ORM\Column(length: 80)] private string $firstName,
        #[ORM\Column(length: 80)] private string $lastName,
    ) {
        $this->vehicles = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getFirstName(): string { return $this->firstName; }
    public function getLastName(): string { return $this->lastName; }
    public function getFullName(): string { return $this->firstName.' '.$this->lastName; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): void { $this->phone = $phone; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $hash): void { $this->password = $hash; }
    public function getDriverCancellations(): int { return $this->driverCancellations; }
    public function incrementDriverCancellations(): void { ++$this->driverCancellations; }
    /** @return Collection<int, Vehicle> */
    public function getVehicles(): Collection { return $this->vehicles; }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): void { $this->roles = $roles; }
    public function hasRole(string $role): bool { return in_array($role, $this->getRoles(), true); }
    /** Un utilisateur est « conducteur » dès qu'il a enregistré un véhicule. */
    public function isDriver(): bool { return !$this->vehicles->isEmpty(); }

    public function sameAs(?self $other): bool
    {
        if (null === $other) {
            return false;
        }

        return $this === $other || (null !== $this->id && $this->id === $other->id);
    }

    public function eraseCredentials(): void
    {
    }

    public function __toString(): string { return $this->getFullName(); }
}
