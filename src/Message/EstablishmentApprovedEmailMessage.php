<?php

declare(strict_types=1);

namespace App\Message;

/** Envoyé au propriétaire quand un admin approuve son établissement. Données simples uniquement (pas d'entité Doctrine dans un message Messenger). */
final class EstablishmentApprovedEmailMessage
{
    /** @param 'pharmacy'|'doctor'|'laboratory' $establishmentType */
    public function __construct(
        public readonly string $userId,
        public readonly string $establishmentType,
        public readonly string $establishmentName,
    ) {
    }
}
