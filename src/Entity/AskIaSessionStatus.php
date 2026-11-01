<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * §25 : persiste l'échange question/réponse pour (a) permettre de
 * reprendre une session si l'app mobile est fermée en cours de route, (b)
 * garder un historique auditable de ce que l'IA a répondu — utile si un
 * jour une réponse posait question (santé = domaine sensible, traçabilité
 * mérite d'être là dès le premier lot plutôt qu'ajoutée après coup).
 */
enum AskIaSessionStatus: string
{
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
}
