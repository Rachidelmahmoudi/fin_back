<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Phase 4b : ajout du mot de passe (hashé, jamais exposé via API Platform —
 * exclu du groupe de sérialisation 'user:read'), et des rôles métier.
 *
 * IMPORTANT sécurité (§18 du cahier des charges) : aucun de ces rôles n'est
 * jamais déduit d'un Bundle ID / identifiant d'app envoyé par le client.
 * Ils sont attribués uniquement côté serveur, soit à l'inscription
 * (ROLE_USER), soit à la publication d'un Establishment
 * (ROLE_PHARMACIST/ROLE_DOCTOR/ROLE_LAB_OWNER, cf. Phase 4c), soit par un
 * admin (ROLE_DECODER, ROLE_ADMIN).
 */
#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    private string $email;

    /** Hash bcrypt/argon2i — jamais retourné par l'API. */
    #[ORM\Column(length: 255)]
    private string $password;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 5, options: ['default' => 'fr'])]
    private string $locale = 'fr';

    /**
     * Points cumulés du déchiffreur (§28 du cahier des charges). Sur User
     * plutôt que sur une entité Decoder séparée : un déchiffreur est un
     * User avec ROLE_DECODER, pas un type d'établissement — pas besoin
     * d'une hiérarchie Establishment ici.
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $decoderPoints = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 2])]
    private int $trialDecodingsRemaining = 2;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $email)
    {
        $this->id = Uuid::v7();
        $this->email = $email;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): static
    {
        $this->password = $hashedPassword;

        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function addRole(string $role): static
    {
        if (!\in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getDecoderPoints(): int
    {
        return $this->decoderPoints;
    }

    public function addDecoderPoints(int $points): static
    {
        $this->decoderPoints += $points;

        return $this;
    }

    public function getTrialDecodingsRemaining(): int
    {
        return $this->trialDecodingsRemaining;
    }

    public function hasTrialDecodingsRemaining(): bool
    {
        return $this->trialDecodingsRemaining > 0;
    }

    public function consumeTrialDecoding(): static
    {
        if ($this->trialDecodingsRemaining > 0) {
            --$this->trialDecodingsRemaining;
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function eraseCredentials(): void
    {
    }
}
