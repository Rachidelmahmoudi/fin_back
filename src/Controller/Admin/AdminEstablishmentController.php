<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Doctor;
use App\Entity\EstablishmentStatus;
use App\Entity\Laboratory;
use App\Entity\Pharmacy;
use App\Entity\PharmacyStatus;
use App\Entity\User;
use App\Message\EstablishmentApprovedEmailMessage;
use App\Service\Admin\AdminSidebarCountsService;
use App\Service\Establishment\EstablishmentModerationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Interface web pour ce qui n'existait jusqu'ici qu'en API
 * (ModerationController, Phase 4c/4g) — même logique métier
 * (EstablishmentModerationService, réutilisé tel quel), juste une vue
 * humaine dessus plutôt que des appels JSON. Un seul controller pour les
 * 3 types plutôt que 3 quasi-identiques, même principe que
 * EstablishmentController (API) et establishment/create.html.twig (Phase 4f).
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminEstablishmentController extends AbstractController
{
    private const TYPE_MAP = [
        'doctors' => [Doctor::class, 'ROLE_DOCTOR', 'admin/doctors.html.twig'],
        'laboratories' => [Laboratory::class, 'ROLE_LAB_OWNER', 'admin/laboratories.html.twig'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EstablishmentModerationService $moderationService,
        private readonly AdminSidebarCountsService $sidebarCounts,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/admin/pharmacies', name: 'admin_pharmacies_pending', methods: ['GET'])]
    public function pharmacies(): Response
    {
        $pharmacies = $this->em->getRepository(Pharmacy::class)->findBy(
            ['status' => PharmacyStatus::PENDING],
            ['createdAt' => 'ASC'],
        );

        return $this->render('admin/pharmacies.html.twig', [
            'pharmacies' => $pharmacies,
            'pendingCounts' => $this->sidebarCounts->get(),
        ]);
    }

    /**
     * Pharmacy ne suit pas EstablishmentModerationService (§Phase 4g,
     * n'implémente pas ModeratableEstablishment) — traitée séparément ici,
     * même logique que ModerationController::approvePharmacy() côté API.
     * L'email de confirmation est envoyé ici (et non dans le service) pour
     * la même raison.
     */
    #[Route('/admin/pharmacies/{id}/approve', name: 'admin_pharmacies_approve', methods: ['POST'])]
    public function approvePharmacy(string $id, #[CurrentUser] User $admin): RedirectResponse
    {
        $pharmacy = $this->em->getRepository(Pharmacy::class)->find($id) ?? throw $this->createNotFoundException();

        if ($pharmacy->getStatus() === PharmacyStatus::PENDING) {
            $pharmacy->setStatus(PharmacyStatus::ACTIVE);
            $owner = $pharmacy->getOwner();
            if ($owner !== null) {
                $owner->addRole('ROLE_PHARMACIST');
                $this->em->persist($owner);
            }
            $this->em->persist($pharmacy);
            $this->em->flush();

            // Après le flush : l'approbation est déjà enregistrée, un problème
            // d'envoi ne doit ni l'annuler ni afficher une erreur à l'admin.
            if ($owner !== null) {
                try {
                    $this->messageBus->dispatch(new EstablishmentApprovedEmailMessage(
                        $owner->getId()->toRfc4122(),
                        'pharmacy',
                        $pharmacy->getName(),
                    ));
                } catch (\Throwable $e) {
                    $this->logger->error('Mise en file de l\'email d\'approbation impossible', ['owner' => $owner->getEmail(), 'exception' => $e->getMessage()]);
                }
            }

            $this->addFlash('success', sprintf('Pharmacie "%s" approuvée.', $pharmacy->getName()));
        }

        return $this->redirectToRoute('admin_pharmacies_pending');
    }

    #[Route('/admin/pharmacies/{id}/suspend', name: 'admin_pharmacies_suspend', methods: ['POST'])]
    public function suspendPharmacy(string $id): RedirectResponse
    {
        $pharmacy = $this->em->getRepository(Pharmacy::class)->find($id) ?? throw $this->createNotFoundException();

        $pharmacy->setStatus(PharmacyStatus::SUSPENDED);
        $this->em->persist($pharmacy);
        $this->em->flush();
        $this->addFlash('success', sprintf('Pharmacie "%s" suspendue.', $pharmacy->getName()));

        return $this->redirectToRoute('admin_pharmacies_pending');
    }

    #[Route('/admin/{type}', name: 'admin_establishment_pending', requirements: ['type' => 'doctors|laboratories'], methods: ['GET'])]
    public function pending(string $type): Response
    {
        [$entityClass, , $template] = self::TYPE_MAP[$type];

        $items = $this->em->getRepository($entityClass)->findBy(
            ['status' => EstablishmentStatus::PENDING_REVIEW],
            ['createdAt' => 'ASC'],
        );

        return $this->render($template, [
            $type => $items,
            'pendingCounts' => $this->sidebarCounts->get(),
        ]);
    }

    #[Route('/admin/{type}/{id}/approve', name: 'admin_establishment_approve', requirements: ['type' => 'doctors|laboratories'], methods: ['POST'])]
    public function approve(string $type, string $id, Request $request, #[CurrentUser] User $admin): RedirectResponse
    {
        [$entityClass, $role] = self::TYPE_MAP[$type];
        $establishment = $this->em->getRepository($entityClass)->find($id) ?? throw $this->createNotFoundException();

        $markVerified = $request->request->getBoolean('markVerified');

        try {
            $this->moderationService->approve($establishment, $admin, $role, $markVerified);
            $this->addFlash('success', 'Établissement approuvé.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_establishment_pending', ['type' => $type]);
    }

    #[Route('/admin/{type}/{id}/reject', name: 'admin_establishment_reject', requirements: ['type' => 'doctors|laboratories'], methods: ['POST'])]
    public function reject(string $type, string $id, Request $request): RedirectResponse
    {
        [$entityClass] = self::TYPE_MAP[$type];
        $establishment = $this->em->getRepository($entityClass)->find($id) ?? throw $this->createNotFoundException();

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $this->addFlash('error', 'Un motif de rejet est requis.');

            return $this->redirectToRoute('admin_establishment_pending', ['type' => $type]);
        }

        try {
            $this->moderationService->reject($establishment, $reason);
            $this->addFlash('success', 'Établissement rejeté.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_establishment_pending', ['type' => $type]);
    }
}
