<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\PrescriptionRequest;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Signature voteOnAttribute() alignée sur Symfony 7.4 — voir le commentaire
 * équivalent dans PharmacyVoter.php pour le détail (4ᵉ paramètre ?Vote
 * introduit après la version 7.1 initialement ciblée par ce projet).
 */
final class PrescriptionRequestVoter extends Voter
{
    /** Voir/soumettre le résultat : réservé au déchiffreur qui l'a acceptée, ou au pharmacien émetteur. */
    public const VIEW_RESULT = 'PRESCRIPTION_VIEW_RESULT';
    public const SUBMIT_RESULT = 'PRESCRIPTION_SUBMIT_RESULT';
    public const CANCEL = 'PRESCRIPTION_CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW_RESULT, self::SUBMIT_RESULT, self::CANCEL], true)
            && $subject instanceof PrescriptionRequest;
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

        /** @var PrescriptionRequest $request */
        $request = $subject;
        $isPharmacist = $request->getPharmacist()->getId()->equals($user->getId());
        $isAssignedDecoder = $request->getDecoder()?->getId()->equals($user->getId()) ?? false;

        return match ($attribute) {
            self::SUBMIT_RESULT => $isAssignedDecoder && \in_array('ROLE_DECODER', $user->getRoles(), true),
            self::VIEW_RESULT => $isPharmacist || $isAssignedDecoder,
            self::CANCEL => $isPharmacist,
            default => false,
        };
    }
}
