<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * §26 du cahier des charges — marketplace pharmacien <-> déchiffreur.
 *
 * Concurrence : le passage PUBLISHED -> ACCEPTED DOIT être atomique pour
 * empêcher deux déchiffreurs d'accepter la même demande. Ce n'est PAS géré
 * ici au niveau entité (Doctrine ne peut pas garantir ça) mais dans
 * PrescriptionMatchingService::accept(), via un UPDATE conditionnel :
 *   UPDATE prescription_request
 *   SET status = 'ACCEPTED', decoder_id = :decoder, accepted_at = NOW(), expires_at = ...
 *   WHERE id = :id AND status = 'PUBLISHED'
 * Si affectedRows === 0, un autre déchiffreur a été plus rapide -> exception
 * métier dédiée, jamais un silent no-op.
 */
enum PrescriptionRequestStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case ACCEPTED = 'ACCEPTED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case EXPIRED = 'EXPIRED';
    case CANCELLED = 'CANCELLED';
}
