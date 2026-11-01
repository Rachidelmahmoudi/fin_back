<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * §Addendum C : statut de modération commun à Doctor et Laboratory.
 * Pharmacy garde volontairement son PharmacyStatus (PENDING/ACTIVE/
 * SUSPENDED) existant depuis la Phase 4a plutôt que d'être migré vers
 * celui-ci maintenant — un renommage a posteriori casserait les données déjà
 * importées (18k+ pharmacies) sans bénéfice fonctionnel réel. À unifier
 * seulement si un vrai besoin transverse apparaît (ex: un back-office de
 * modération commun aux 3 types), pas par souci de symétrie du code.
 */
enum EstablishmentStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_REVIEW = 'PENDING_REVIEW';
    case PUBLISHED = 'PUBLISHED';
    case REJECTED = 'REJECTED';
    case SUSPENDED = 'SUSPENDED';
}
