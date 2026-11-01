<?php

declare(strict_types=1);

namespace App\Message;

final class WalletCreditedEmailMessage
{
    public function __construct(
        public readonly string $userId,
        public readonly string $creditedAmount,
        public readonly string $newBalance,
    ) {
    }
}
