<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Doctor;
use App\Entity\Laboratory;
use App\Entity\ModeratableEstablishment;
use App\Entity\User;
use App\Service\Establishment\EstablishmentModerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Back-office admin (§Addendum D). Un seul controller pour Doctor et
 * Laboratory grâce à ModeratableEstablishment + EstablishmentModerationService
 * — c'est exactement le bénéfice recherché en construisant cette interface
 * commune plutôt que deux services de modération dupliqués.
 */
#[IsGranted('ROLE_ADMIN')]
final class ModerationController extends AbstractController
{
    private const TYPE_MAP = [
        'doctor' => [Doctor::class, 'ROLE_DOCTOR'],
        'laboratory' => [Laboratory::class, 'ROLE_LAB_OWNER'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EstablishmentModerationService $moderationService,
    ) {
    }

    #[Route('/api/v1/admin/establishments/pending', name: 'api_admin_establishments_pending', methods: ['GET'])]
    public function pending(Request $request): JsonResponse
    {
        $type = $request->query->get('type', 'doctor');
        [$entityClass] = self::TYPE_MAP[$type] ?? [null, null];
        if ($entityClass === null) {
            return new JsonResponse(['error' => 'type doit être "doctor" ou "laboratory".'], 422);
        }

        $items = $this->em->getRepository($entityClass)->findBy(
            ['status' => \App\Entity\EstablishmentStatus::PENDING_REVIEW],
            ['createdAt' => 'ASC'],
        );

        return new JsonResponse(array_map(
            static fn (ModeratableEstablishment $e) => [
                'id' => $e->getId()->toRfc4122(),
                'name' => method_exists($e, 'getName') ? $e->getName() : null,
                'owner' => $e->getOwner()?->getEmail(),
            ],
            $items,
        ));
    }

    #[Route('/api/v1/admin/establishments/{type}/{id}/approve', name: 'api_admin_establishments_approve', methods: ['POST'])]
    public function approve(string $type, string $id, Request $request, #[CurrentUser] User $admin): JsonResponse
    {
        [$entityClass, $role] = self::TYPE_MAP[$type] ?? [null, null];
        if ($entityClass === null) {
            return new JsonResponse(['error' => 'type doit être "doctor" ou "laboratory".'], 422);
        }

        $establishment = $this->em->getRepository($entityClass)->find($id)
            ?? throw $this->createNotFoundException();

        $payload = json_decode($request->getContent() ?: '{}', true);
        $markVerified = (bool) ($payload['markVerified'] ?? false);

        try {
            $this->moderationService->approve($establishment, $admin, $role, $markVerified);
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse(['status' => $establishment->getStatus()->value]);
    }

    #[Route('/api/v1/admin/establishments/{type}/{id}/reject', name: 'api_admin_establishments_reject', methods: ['POST'])]
    public function reject(string $type, string $id, Request $request): JsonResponse
    {
        [$entityClass] = self::TYPE_MAP[$type] ?? [null, null];
        if ($entityClass === null) {
            return new JsonResponse(['error' => 'type doit être "doctor" ou "laboratory".'], 422);
        }

        $establishment = $this->em->getRepository($entityClass)->find($id)
            ?? throw $this->createNotFoundException();

        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($reason === '') {
            return new JsonResponse(['error' => 'reason est requis pour un rejet.'], 422);
        }

        try {
            $this->moderationService->reject($establishment, $reason);
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse(['status' => $establishment->getStatus()->value]);
    }

    /**
     * §Phase 4g — Pharmacy n'implémente pas ModeratableEstablishment (cf.
     * EstablishmentController::createPharmacy()), donc traitée séparément
     * ici plutôt que forcée dans TYPE_MAP/EstablishmentModerationService.
     * PharmacyStatus n'a pas de REJECTED : le choix côté pharmacie est
     * uniquement approuver (PENDING -> ACTIVE) ou suspendre à tout moment
     * (§Phase 4a, statut déjà pensé ainsi), pas de rejet formel distinct
     * d'une suspension.
     */
    #[Route('/api/v1/admin/establishments/pharmacy/pending', name: 'api_admin_pharmacy_pending', methods: ['GET'])]
    public function pendingPharmacies(): JsonResponse
    {
        $pharmacies = $this->em->getRepository(\App\Entity\Pharmacy::class)->findBy(
            ['status' => \App\Entity\PharmacyStatus::PENDING],
            ['createdAt' => 'ASC'],
        );

        return new JsonResponse(array_map(
            static fn (\App\Entity\Pharmacy $p) => [
                'id' => $p->getId()->toRfc4122(),
                'name' => $p->getName(),
                'owner' => $p->getOwner()?->getEmail(),
            ],
            $pharmacies,
        ));
    }

    #[Route('/api/v1/admin/establishments/pharmacy/{id}/approve', name: 'api_admin_pharmacy_approve', methods: ['POST'])]
    public function approvePharmacy(string $id): JsonResponse
    {
        $pharmacy = $this->em->getRepository(\App\Entity\Pharmacy::class)->find($id)
            ?? throw $this->createNotFoundException();

        if ($pharmacy->getStatus() !== \App\Entity\PharmacyStatus::PENDING) {
            return new JsonResponse(['error' => 'Cette pharmacie n\'est pas en attente de modération.'], 422);
        }

        $pharmacy->setStatus(\App\Entity\PharmacyStatus::ACTIVE);
        $owner = $pharmacy->getOwner();
        if ($owner !== null) {
            $owner->addRole('ROLE_PHARMACIST');
            $this->em->persist($owner);
        }
        $this->em->persist($pharmacy);
        $this->em->flush();

        return new JsonResponse(['status' => $pharmacy->getStatus()->value]);
    }

    #[Route('/api/v1/admin/establishments/pharmacy/{id}/suspend', name: 'api_admin_pharmacy_suspend', methods: ['POST'])]
    public function suspendPharmacy(string $id): JsonResponse
    {
        $pharmacy = $this->em->getRepository(\App\Entity\Pharmacy::class)->find($id)
            ?? throw $this->createNotFoundException();

        $pharmacy->setStatus(\App\Entity\PharmacyStatus::SUSPENDED);
        $this->em->persist($pharmacy);
        $this->em->flush();

        return new JsonResponse(['status' => $pharmacy->getStatus()->value]);
    }
}
