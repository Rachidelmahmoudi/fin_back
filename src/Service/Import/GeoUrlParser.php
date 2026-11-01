<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Parse "https://maps.google.com/maps?q=30.42110062,-9.58306026" en [lat, lng].
 * Champ googleMapsUrl de pharmacies.json — voir Phase 1 de l'analyse.
 */
final class GeoUrlParser
{
    /** @return array{0: string, 1: string}|null */
    public static function parse(?string $googleMapsUrl): ?array
    {
        if ($googleMapsUrl === null) {
            return null;
        }

        if (!preg_match('/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/', $googleMapsUrl, $m)) {
            return null;
        }

        return [$m[1], $m[2]];
    }
}
