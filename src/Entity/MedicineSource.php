<?php

declare(strict_types=1);

namespace App\Entity;

/** Origine d'une ligne medicine_source_record (fichier dédié : l'enum doit être autochargeable). */
enum MedicineSource: string
{
    case MEDICAMENT_MA = 'MEDICAMENT_MA';
    case CNSS_GUIDE = 'CNSS_GUIDE';
    case CNSS_GUIDE_XLSX = 'CNSS_GUIDE_XLSX';
    case CNSS_GUIDE_PDF = 'CNSS_GUIDE_PDF';
}
