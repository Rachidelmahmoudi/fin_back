<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Doctor;
use App\Entity\Laboratory;
use App\Entity\Pharmacy;
use App\Entity\PharmacyStatus;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Point d'entrée unique de "Mon établissement" (web + mobile) : liste TOUS
 * les établissements de l'utilisateur connecté, quel que soit leur type et
 * leur statut — y compris ceux encore en attente de validation.
 *
 * Volontairement ouvert à tout compte connecté (ROLE_USER), PAS réservé à
 * ROLE_PHARMACIST/DOCTOR/LAB_OWNER : ces rôles ne sont attribués qu'à
 * l'approbation, donc un propriétaire dont l'établissement est encore en
 * attente n'a aucun de ces rôles — et c'est justement lui qui a besoin de
 * voir "en attente de validation" ou le motif d'un rejet. L'autorisation
 * repose sur la propriété réelle (champ owner en base), pas sur un rôle.
 */
#[IsGranted('ROLE_USER')]
final class MyEstablishmentsController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/v1/me/establishments', name: 'api_me_establishments', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        $items = [];

        /** @var Pharmacy $p */
        foreach ($this->em->getRepository(Pharmacy::class)->findBy(['owner' => $user], ['createdAt' => 'DESC']) as $p) {
            $items[] = [
                'type' => 'pharmacy',
                'id' => $p->getId()->toRfc4122(),
                'name' => $p->getName(),
                // Pharmacy garde son propre enum (PENDING/ACTIVE/SUSPENDED) : normalisé ici
                // vers le même vocabulaire que Doctor/Laboratory pour que les clients n'aient qu'un seul jeu de statuts.
                'status' => match ($p->getStatus()) {
                    PharmacyStatus::PENDING => 'PENDING_REVIEW',
                    PharmacyStatus::ACTIVE => 'PUBLISHED',
                    PharmacyStatus::SUSPENDED => 'SUSPENDED',
                },
                'rejectionReason' => null,
                'cityName' => $p->getCity()->getName(),
                'publicSlug' => $p->getSlug().'-'.$p->getUid(),
                'photoUrl' => $p->getPhoto(),
            ];
        }

        /** @var Doctor $d */
        foreach ($this->em->getRepository(Doctor::class)->findBy(['owner' => $user], ['createdAt' => 'DESC']) as $d) {
            $items[] = [
                'type' => 'doctor',
                'id' => $d->getId()->toRfc4122(),
                'name' => $d->getName(),
                'status' => $d->getStatus()->value,
                'rejectionReason' => $d->getRejectionReason(),
                'cityName' => $d->getCity()->getName(),
                'publicSlug' => $d->getSlug().'-'.$d->getUid(),
                'photoUrl' => $d->getPhotoUrls()[0] ?? null,
            ];
        }

        /** @var Laboratory $l */
        foreach ($this->em->getRepository(Laboratory::class)->findBy(['owner' => $user], ['createdAt' => 'DESC']) as $l) {
            $items[] = [
                'type' => 'laboratory',
                'id' => $l->getId()->toRfc4122(),
                'name' => $l->getName(),
                'status' => $l->getStatus()->value,
                'rejectionReason' => $l->getRejectionReason(),
                'cityName' => $l->getCity()->getName(),
                'publicSlug' => $l->getSlug().'-'.$l->getUid(),
                'photoUrl' => $l->getPhotoUrls()[0] ?? null,
            ];
        }

        return new JsonResponse($items);
    }
}
