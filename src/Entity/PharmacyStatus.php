<?php

declare(strict_types=1);

namespace App\Entity;

enum PharmacyStatus: string
{
    case PENDING = 'PENDING';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
}
