<?php

declare(strict_types=1);

namespace App\Service\Establishment;

use App\Entity\Doctor;
use App\Entity\EstablishmentStatus;
use App\Entity\ModeratableEstablishment;
use App\Entity\User;
use App\Message\EstablishmentApprovedEmailMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * §Addendum D — "workflow de soumission commun aux 3 types
 * d'établissement". Ce service applique le même cycle de vie à Doctor et
 * Laboratory via l'interface ModeratableEstablishment (Pharmacy suit son
 * propre cycle depuis la Phase 4a, cf. commentaire dans Doctor.php).
 *
 * Cycle : DRAFT -> PENDING_REVIEW (à la soumission) -> PUBLISHED (admin
 * approuve) ou REJECTED (admin rejette, avec motif) -> SUSPENDED (admin,
 * à tout moment, ex. signalement).
 *
 * §Addendum 2, décision confirmée : republication après édition = directe.
 * Ce service ne gère donc QUE la modération de la création initiale ;
 * l'édition ultérieure par le propriétaire ne repasse jamais par ici
 * (voir les futurs DoctorPatchProcessor/LaboratoryPatchProcessor, sur le
 * même modèle que PharmacyPatchProcessor, Phase 4b).
 */
final class EstablishmentModerationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Soumission initiale : DRAFT -> PENDING_REVIEW. Appelé par le
     * controller de création, jamais directement PUBLISHED — même un
     * établissement rempli parfaitement passe par la file de modération
     * (§Addendum D : "création de profil ; formulaire d'établissement ;
     * validation/modération").
     */
    public function submitForReview(ModeratableEstablishment $establishment): void
    {
        if ($establishment->getStatus() !== EstablishmentStatus::DRAFT) {
            throw new \DomainException('Seul un établissement en brouillon peut être soumis à modération.');
        }

        $establishment->setStatus(EstablishmentStatus::PENDING_REVIEW);
        $this->em->persist($establishment);
        $this->em->flush();
    }

    /**
     * Approbation par un admin. Attribue le rôle métier correspondant à
     * l'utilisateur propriétaire (§Addendum D : "Lorsqu'un médecin ajoute
     * son cabinet, ses informations seront enregistrées... [et il obtient
     * accès à] son espace de gestion").
     *
     * Envoie ensuite un email de confirmation au propriétaire — APRÈS le
     * flush : l'approbation est déjà enregistrée, un problème d'envoi ne
     * doit jamais l'annuler ni afficher une erreur à l'admin.
     */
    public function approve(ModeratableEstablishment $establishment, User $admin, string $grantedRole, bool $markVerified = false): void
    {
        if ($establishment->getStatus() !== EstablishmentStatus::PENDING_REVIEW) {
            throw new \DomainException('Seul un établissement en attente de modération peut être approuvé.');
        }

        $establishment->setStatus(EstablishmentStatus::PUBLISHED);
        if ($markVerified) {
            $establishment->markVerified($admin);
        }

        $owner = $establishment->getOwner();
        if ($owner !== null) {
            $owner->addRole($grantedRole);
            $this->em->persist($owner);
        }

        $this->em->persist($establishment);
        $this->em->flush();

        if ($owner !== null && method_exists($establishment, 'getName')) {
            $this->notifyApproved($owner, $establishment instanceof Doctor ? 'doctor' : 'laboratory', (string) $establishment->getName());
        }
    }

    public function reject(ModeratableEstablishment $establishment, string $reason): void
    {
        if ($establishment->getStatus() !== EstablishmentStatus::PENDING_REVIEW) {
            throw new \DomainException('Seul un établissement en attente de modération peut être rejeté.');
        }

        $establishment->setStatus(EstablishmentStatus::REJECTED);
        if (method_exists($establishment, 'setRejectionReason')) {
            $establishment->setRejectionReason($reason);
        }

        $this->em->persist($establishment);
        $this->em->flush();
    }

    /** Suspension à tout moment (signalement, établissement fermé, etc.) — n'affecte pas isVerified. */
    public function suspend(ModeratableEstablishment $establishment): void
    {
        $establishment->setStatus(EstablishmentStatus::SUSPENDED);
        $this->em->persist($establishment);
        $this->em->flush();
    }

    private function notifyApproved(User $owner, string $type, string $name): void
    {
        try {
            $this->messageBus->dispatch(new EstablishmentApprovedEmailMessage($owner->getId()->toRfc4122(), $type, $name));
        } catch (\Throwable $e) {
            $this->logger->error('Mise en file de l\'email d\'approbation impossible', ['owner' => $owner->getEmail(), 'exception' => $e->getMessage()]);
        }
    }
}
