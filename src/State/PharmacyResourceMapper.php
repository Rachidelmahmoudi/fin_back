<?php

declare(strict_types=1);

namespace App\State;

use App\ApiResource\PharmacyResource;
use App\Entity\Pharmacy;

/**
 * Mapping entité -> DTO centralisé pour éviter de le dupliquer entre le
 * provider de collection et le provider d'item (Phase 3, §3.2 — DTO-first).
 *
 * isOpenNow/isOnDutyNow sont lus depuis les flags dénormalisés de l'entité
 * (Pharmacy::isOpenNow()/isOnDutyNow()), recalculés par
 * PharmacyAvailabilityService via un job planifié (§Phase 3, "statut calculé,
 * pas seulement stocké") — pas recalculés à la volée à chaque requête API,
 * pour ne pas payer le coût du calcul horaire sur chaque item d'un listing.
 *
 * `services` (PharmacyService M2M, §Addendum C) n'est pas encore modélisé en
 * Phase 4a/4b — retourne un tableau vide en attendant cette entité.
 */
final class PharmacyResourceMapper
{
    public static function map(Pharmacy $pharmacy, ?float $distanceKm = null): PharmacyResource
    {
        $resource = new PharmacyResource();
        $resource->id = $pharmacy->getId()->toRfc4122();
        $resource->slug = $pharmacy->getSlug().'-'.$pharmacy->getUid();
        $resource->name = $pharmacy->getName();
        $resource->addressRaw = $pharmacy->getAddressRaw();
        $resource->district = $pharmacy->getDistrict();
        $resource->cityName = $pharmacy->getCity()->getName();
        $resource->citySlug = $pharmacy->getCity()->getSlug();
        $resource->lat = $pharmacy->getLat() !== null ? (float) $pharmacy->getLat() : null;
        $resource->lng = $pharmacy->getLng() !== null ? (float) $pharmacy->getLng() : null;
        $resource->distanceKm = $distanceKm;
        $resource->phone = $pharmacy->getPhone();
        $resource->email = $pharmacy->getEmail();
        $resource->website = $pharmacy->getWebsite();
        $resource->photoUrl = $pharmacy->getPhoto();
        $resource->isVerified = $pharmacy->isVerified();
        $resource->isOpenNow = $pharmacy->isOpenNow();
        $resource->isOnDutyNow = $pharmacy->isOnDutyNow();
        $resource->services = []; // TODO Phase 4c : PharmacyService M2M

        return $resource;
    }
}
