<?php

declare(strict_types=1);

namespace App\Entity;

enum PrincepsOrGeneric: string
{
    case PRINCEPS = 'P';
    case GENERIC = 'G';
}
