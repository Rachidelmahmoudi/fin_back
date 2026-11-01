<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\PrescriptionRequest;
use App\Entity\PrescriptionResult;
use App\Service\Admin\AdminSidebarCountsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Historique des déchiffrements — traçabilité complète (demandeur,
 * ordonnance, déchiffreur, résultat, date) pour pouvoir revenir sur un
 * déchiffrement en cas de litige signalé par un pharmacien. Lecture seule :
 * aucune action de modération ici, contrairement aux autres écrans admin —
 * le déchiffrement est déjà terminé au moment où il apparaît ici.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminPrescriptionController extends AbstractController
{
    private const PAGE_SIZE = 30;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminSidebarCountsService $sidebarCounts,
    ) {
    }

    #[Route('/admin/prescriptions', name: 'admin_prescriptions_history', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));

        $qb = $this->em->getRepository(PrescriptionRequest::class)->createQueryBuilder('r')
            ->leftJoin('r.pharmacist', 'ph')->addSelect('ph')
            ->leftJoin('r.decoder', 'd')->addSelect('d')
            ->orderBy('r.createdAt', 'DESC');

        $totalCount = (int) (clone $qb)
            ->select('COUNT(r.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $requests = $qb
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()
            ->getResult();

        // Résultats associés — récupérés séparément et indexés par id de
        // demande plutôt que de supposer une association inverse directe
        // sur PrescriptionRequest (cet écran a été construit sans modifier
        // les entités existantes).
        $requestIds = array_map(static fn (PrescriptionRequest $r) => $r->getId(), $requests);
        $results = $requestIds !== []
            ? $this->em->getRepository(PrescriptionResult::class)->createQueryBuilder('res')
                ->andWhere('res.request IN (:ids)')
                ->setParameter('ids', $requestIds)
                ->getQuery()
                ->getResult()
            : [];

        $resultsByRequestId = [];
        /** @var PrescriptionResult $result */
        foreach ($results as $result) {
            $resultsByRequestId[$result->getRequest()->getId()->toRfc4122()] = $result;
        }

        $rows = array_map(
            static fn (PrescriptionRequest $r) => [
                'request' => $r,
                'result' => $resultsByRequestId[$r->getId()->toRfc4122()] ?? null,
            ],
            $requests,
        );

        return $this->render('admin/prescriptions.html.twig', [
            'rows' => $rows,
            'page' => $page,
            'totalPages' => max(1, (int) ceil($totalCount / self::PAGE_SIZE)),
            'totalCount' => $totalCount,
            'pendingCounts' => $this->sidebarCounts->get(),
        ]);
    }
}
