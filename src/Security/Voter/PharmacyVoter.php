<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Pharmacy;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * §18 du cahier des charges : "un pharmacien ne peut modifier que SA
 * pharmacie — vérifié par un Voter, pas par un simple check d'ID côté
 * client." C'est exactement ce que ce Voter garantit, branché sur
 * l'ApiResource Pharmacy via #[ApiResource(security: "...")] /
 * #[Patch(security: "is_granted('PHARMACY_EDIT', object)")] (Phase 4c).
 *
 * Signature voteOnAttribute() alignée sur Symfony 7.4 (4ᵉ paramètre
 * ?Vote $vote — nouveau système d'explication de vote introduit après la
 * version 7.1 initialement ciblée) — sans ce paramètre, PHP refuse de
 * charger la classe (erreur de compatibilité de signature avec la classe
 * abstraite parente).
 */
final class PharmacyVoter extends Voter
{
    public const EDIT = 'PHARMACY_EDIT';
    public const MANAGE_STOCK = 'PHARMACY_MANAGE_STOCK';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::MANAGE_STOCK], true)
            && $subject instanceof Pharmacy;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        /** @var Pharmacy $pharmacy */
        $pharmacy = $subject;

        // L'égalité se fait sur l'UUID, jamais sur une donnée envoyée par
        // le client (ex: un pharmacyId dans le body de la requête) — le
        // seul "propriétaire de vérité" est Pharmacy::owner en base.
        return $pharmacy->getOwner()?->getId()->equals($user->getId()) ?? false;
    }
}