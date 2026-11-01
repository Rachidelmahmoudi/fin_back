<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * CREDIT/DEBIT/REFUND = mouvements réels sur balance.
 * HOLD/RELEASE = mouvements sur holdBalance uniquement (réservation avant
 * débit effectif), introduits pour le flux "débit à la validation du
 * déchiffreur" confirmé dans l'Addendum 2 :
 *   1. Pharmacien publie -> pas de mouvement, juste vérif balance>=5DH.
 *   2. Déchiffreur accepte -> HOLD (montant estimé = itemsCount x 1 DH).
 *   3. Déchiffreur valide  -> RELEASE du hold + DEBIT réel (montant exact).
 *   4. Expiration/annulation avant validation -> RELEASE seul, aucun DEBIT.
 */
enum WalletTransactionType: string
{
    case CREDIT = 'CREDIT';
    case DEBIT = 'DEBIT';
    case REFUND = 'REFUND';
    case HOLD = 'HOLD';
    case RELEASE = 'RELEASE';
}
