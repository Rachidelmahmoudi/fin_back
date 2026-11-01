<?php

declare(strict_types=1);

namespace App\Entity;

enum WalletRechargeStatus: string
{
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case REJECTED = 'REJECTED';
}
