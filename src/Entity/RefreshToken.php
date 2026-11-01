<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Refresh token rotatif (§18 du cahier des charges — sécurité API mobile).
 *
 * Principes appliqués :
 * - Jamais stocké en clair : seul le hash SHA-256 du token est persisté.
 *   Le token brut n'est renvoyé au client qu'une seule fois, à l'émission.
 * - Rotation : chaque utilisation invalide l'ancien et en émet un nouveau
 *   (voir RefreshTokenService en Phase 4c pour la logique de rotation ---
 *   ce fichier ne modélise que l'état, pas le flux).
 * - revokedAt permet une révocation immédiate (déconnexion "partout",
 *   compromission suspectée) sans attendre l'expiration naturelle.
 * - Un access token JWT (courte durée, ~15 min, stateless) est émis à
 *   chaque échange de refresh token valide ; il n'est PAS stocké en base.
 */
#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_token')]
#[ORM\Index(name: 'idx_refresh_token_hash', columns: ['token_hash'])]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    /** Device/app instance ayant demandé ce token — pour un futur "déconnecter cet appareil". */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $deviceLabel = null;

    public function __construct(User $user, string $tokenHash, \DateTimeImmutable $expiresAt, ?string $deviceLabel = null)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->deviceLabel = $deviceLabel;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isValid(): bool
    {
        return $this->revokedAt === null && $this->expiresAt > new \DateTimeImmutable();
    }

    public function revoke(): static
    {
        $this->revokedAt = new \DateTimeImmutable();

        return $this;
    }
}
