<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\City;
use App\Entity\Pharmacy;
use App\Entity\PharmacyStatus;
use App\Repository\CityRepository;
use App\Repository\PharmacyRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Import idempotent depuis pharmacies.json (annuaire-gratuit.ma).
 *
 * Stratégie de dédup (voir Phase 1 de l'analyse — doublons probables sur
 * 18k+ lignes, adresses très proches) :
 *  1. Si legacyReference déjà connue -> upsert direct (ré-import du même run).
 *  2. Sinon, on cherche une pharmacie existante avec un nom normalisé très
 *     proche + une géoloc à moins de ~30m dans la même ville : on la
 *     considère comme doublon et on NE crée PAS de nouvelle ligne, on
 *     enrichit l'existante si des champs sont vides.
 *  3. Sinon, création.
 *
 * Le calcul de proximité se fait en PHP (distance haversine) plutôt qu'en
 * SQL pour rester simple ici ; sur un import de 18k lignes avec un index
 * géographique (lat/lng) et un pré-filtre par ville, c'est largement
 * suffisant en performance pour un import batch (pas une requête temps réel).
 */
final class PharmacyImportService
{
    private const DUPLICATE_DISTANCE_METERS = 30.0;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CityRepository $cityRepository,
        private readonly PharmacyRepository $pharmacyRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $row une ligne brute de pharmacies.json
     * @return array{pharmacy: Pharmacy, action: 'created'|'updated'|'merged_duplicate'}
     */
    public function importRow(array $row): array
    {
        $name = trim((string) ($row['name'] ?? ''));
        $cityName = trim((string) ($row['city'] ?? ''));
        $legacyRef = $row['reference'] ?? null;

        if ($name === '' || $cityName === '') {
            throw new \InvalidArgumentException('name/city manquant, ligne ignorée: ' . json_encode($row));
        }

        $city = $this->resolveCity($cityName);
        $geo = GeoUrlParser::parse($row['googleMapsUrl'] ?? null);

        // 1. Déjà importée sous cette référence -> upsert direct
        if ($legacyRef !== null && $existing = $this->pharmacyRepository->findOneByLegacyReference((string) $legacyRef)) {
            $this->fillFromRow($existing, $row, $geo);
            return ['pharmacy' => $existing, 'action' => 'updated'];
        }

        // 2. Recherche de doublon proche (même ville, nom similaire, géoloc proche)
        if ($geo !== null && $duplicate = $this->findLikelyDuplicate($name, $city, $geo)) {
            $this->fillFromRow($duplicate, $row, $geo, onlyIfEmpty: true);
            if ($legacyRef !== null && $duplicate->getLegacyReference() === null) {
                $duplicate->setLegacyReference((string) $legacyRef);
            }
            return ['pharmacy' => $duplicate, 'action' => 'merged_duplicate'];
        }

        // 3. Création
        $slug = SlugHelper::slugify($name);
        $uid = SlugHelper::shortUid();
        $pharmacy = new Pharmacy($uid, $slug . '-' . $uid, $name, trim((string) ($row['address'] ?? $name)), $city);
        $pharmacy->setStatus(PharmacyStatus::ACTIVE); // import initial = déjà considéré actif (source publique connue)
        $this->fillFromRow($pharmacy, $row, $geo);
        if ($legacyRef !== null) {
            $pharmacy->setLegacyReference((string) $legacyRef);
        }

        $this->em->persist($pharmacy);

        return ['pharmacy' => $pharmacy, 'action' => 'created'];
    }

    private function fillFromRow(Pharmacy $pharmacy, array $row, ?array $geo, bool $onlyIfEmpty = false): void
    {
        $phone = isset($row['phone']) ? trim((string) $row['phone']) : null;
        if ($phone !== '' && (!$onlyIfEmpty || $pharmacy->getPhone() === null)) {
            $pharmacy->setPhone($phone);
        }

        $address = isset($row['address']) ? trim((string) $row['address']) : null;
        if ($address !== null && $address !== '' && !$onlyIfEmpty) {
            $pharmacy->setAddressRaw($address);
        }

        if ($geo !== null && (!$onlyIfEmpty || $pharmacy->getLat() === null)) {
            [$lat, $lng] = $geo;
            $pharmacy->setLat($lat);
            $pharmacy->setLng($lng);
        }
    }

    private function resolveCity(string $cityName): City
    {
        $slug = SlugHelper::slugify($cityName);
        $city = $this->cityRepository->findOneBySlug($slug);
        if ($city !== null) {
            return $city;
        }

        $city = new City($cityName, $slug);
        // Une ville créée via l'import pharmacies devient active : elle a
        // au moins un établissement réel (cf. §11 — pas de pages sans contenu).
        $city->setIsActive(true);
        $this->em->persist($city);

        return $city;
    }

    private function findLikelyDuplicate(string $name, City $city, array $geo): ?Pharmacy
    {
        [$lat, $lng] = array_map('floatval', $geo);
        $normalizedTarget = $this->normalizeName($name);

        // Pré-filtre large par ville pour limiter le volume comparé, puis
        // vérification fine nom+distance. Sur un vrai import, remplacer par
        // une requête bounding-box SQL avant de charger en mémoire.
        $candidates = $this->pharmacyRepository->findBy(['city' => $city]);

        foreach ($candidates as $candidate) {
            if ($candidate->getLat() === null || $candidate->getLng() === null) {
                continue;
            }

            $distance = $this->haversineMeters(
                $lat,
                $lng,
                (float) $candidate->getLat(),
                (float) $candidate->getLng(),
            );

            if ($distance > self::DUPLICATE_DISTANCE_METERS) {
                continue;
            }

            similar_text($normalizedTarget, $this->normalizeName($candidate->getName()), $percent);
            if ($percent >= 85.0) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower($name);
        $name = preg_replace('/^pharmacie\s+/u', '', $name) ?? $name;
        return trim($name);
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
