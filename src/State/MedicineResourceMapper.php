<?php

declare(strict_types=1);

namespace App\State;

use App\ApiResource\MedicineResource;
use App\Entity\Medicine;

final class MedicineResourceMapper
{
    public static function map(Medicine $medicine): MedicineResource
    {
        $resource = new MedicineResource();
        $resource->id = $medicine->getId()->toRfc4122();
        $resource->slug = $medicine->getSlug().'-'.$medicine->getUid();
        $resource->name = $medicine->getName();
        $resource->dciName = $medicine->getDci()?->getName();
        $resource->dosageValue = $medicine->getDosageValue() !== null ? (float) $medicine->getDosageValue() : null;
        $resource->dosageUnit = $medicine->getDosageUnit();
        $resource->form = $medicine->getForm();
        $resource->presentation = $medicine->getPresentation();
        $resource->manufacturer = $medicine->getManufacturer();
        $resource->therapeuticClass = $medicine->getTherapeuticClass();
        $resource->tableau = $medicine->getTableau()?->value;
        $resource->indications = $medicine->getIndications();
        $resource->contraindications = $medicine->getContraindications();
        $resource->precautions = $medicine->getPrecautions();

        // Prix affiché : priorité au tarif officiel CNSS (PPV) sur le prix
        // affiché par le site tiers medicament.ma, potentiellement moins à
        // jour ou moins fiable pour un usage commercial (§Addendum §B).
        $resource->price = $medicine->getPpvCnss() !== null
            ? (float) $medicine->getPpvCnss()
            : ($medicine->getPriceCommercial() !== null ? (float) $medicine->getPriceCommercial() : null);

        $resource->isCnssReimbursed = $medicine->getPpvCnss() !== null;
        $resource->reimbursementRate = $medicine->getReimbursementRate() !== null ? (float) $medicine->getReimbursementRate() : null;
        $resource->needsReview = $medicine->needsReview();

        return $resource;
    }
}
