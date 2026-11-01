<?php

declare(strict_types=1);

namespace App\Message;

final class EstablishmentCreatedEmailMessage
{
    /** @param 'pharmacy'|'doctor'|'laboratory' $establishmentType */
    public function __construct(
        public readonly string $userId,
        public readonly string $establishmentType,
        public readonly string $establishmentName,
    ) {
    }
}
