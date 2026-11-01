<?php

declare(strict_types=1);

namespace App\Service\Search;

use App\Entity\Pharmacy;

/**
 * @property Pharmacy[] $items
 */
final class PharmacySearchResult
{
    /**
     * @param Pharmacy[] $items
     * @param array<string, float> $distances clé = Pharmacy id (RFC4122), valeur = distance en km
     */
    public function __construct(
        public readonly array $items,
        public readonly array $distances,
        public readonly int $totalCount,
    ) {
    }
}
