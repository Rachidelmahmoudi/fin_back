<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Implémentée par Doctor et Laboratory pour que
 * EstablishmentModerationService puisse approuver/rejeter les deux types
 * via un seul service, sans dupliquer la logique de transition d'état
 * (§Addendum D : "workflow de soumission commun aux 3 types
 * d'établissement").
 */
interface ModeratableEstablishment
{
    public function getId(): \Symfony\Component\Uid\Uuid;

    public function getStatus(): EstablishmentStatus;

    public function setStatus(EstablishmentStatus $status): static;

    public function getOwner(): ?User;

    public function markVerified(User $admin): static;

    public function isVerified(): bool;
}