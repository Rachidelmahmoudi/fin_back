<?php

declare(strict_types=1);

namespace App\Service\Prescription;

/**
 * Levée quand l'UPDATE conditionnel atomique de PrescriptionMatchingService::accept()
 * affecte 0 ligne — un autre déchiffreur a accepté la demande en premier.
 * À mapper en HTTP 409 Conflict côté controller/ApiResource, avec un
 * message clair pour l'app mobile ("Cette ordonnance vient d'être prise par
 * quelqu'un d'autre").
 */
final class PrescriptionAlreadyAcceptedException extends \RuntimeException
{
}
