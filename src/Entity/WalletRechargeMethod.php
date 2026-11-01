<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Recharge manuelle uniquement (décision explicite : pas de gateway
 * automatique pour l'instant — PayPal ou virement bancaire, reçu envoyé sur
 * WhatsApp, rechargement fait à la main via un petit back-office admin).
 *
 * Cette entité ne DÉCLENCHE rien automatiquement : elle donne juste au
 * back-office admin une liste de "demandes en attente" à rapprocher du
 * reçu WhatsApp reçu manuellement, plutôt que de forcer l'admin à créditer
 * un utilisateur "à l'aveugle" sans traçabilité de ce qui a été annoncé.
 * L'admin, une fois le reçu vérifié, appelle WalletController::credit()
 * (déjà existant, Phase 4b) puis marque cette demande CONFIRMED.
 */
enum WalletRechargeMethod: string
{
    case PAYPAL = 'PAYPAL';
    case BANK_TRANSFER = 'BANK_TRANSFER';
    // §Correctif : nouveau cas pour les recharges créditées directement par
    // l'admin depuis un reçu WhatsApp, sans WalletRechargeRequest déclarée
    // au préalable par l'utilisateur (voir AdminWalletController::creditDirect()).
    case MANUAL = 'MANUAL';
}
