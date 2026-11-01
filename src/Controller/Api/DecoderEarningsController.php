<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\PrescriptionResult;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * §Points déchiffreur — décision confirmée : reste un système de points
 * (User.decoderPoints), pas une conversion en argent réel. Le total
 * affiché est directement le compteur déjà incrémenté de façon fiable par
 * PrescriptionMatchingService::submitResult() — pas une somme recalculée
 * depuis l'historique, pour rester cohérent avec la même source de vérité
 * que WalletController::me() côté pharmacien (affiche le solde stocké,
 * pas un recalcul).
 */
#[IsGranted('ROLE_DECODER')]
final class DecoderEarningsController extends AbstractController
{
    private const PAGE_SIZE = 20;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/v1/decoder/earnings', name: 'api_decoder_earnings', methods: ['GET'])]
    public function me(#[CurrentUser] User $decoder): JsonResponse
    {
        return new JsonResponse([
            'totalPoints' => $decoder->getDecoderPoints(),
            'completedCount' => $this->countCompleted($decoder),
        ]);
    }

    /** Historique détaillé — un déchiffrement = une ligne (date, nb de médicaments identifiés, points gagnés). */
    #[Route('/api/v1/decoder/earnings/history', name: 'api_decoder_earnings_history', methods: ['GET'])]
    public function history(Request $request, #[CurrentUser] User $decoder): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));

        $results = $this->resultsQuery($decoder)
            ->orderBy('res.submittedAt', 'DESC')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()
            ->getResult();

        return new JsonResponse(array_map(
            static fn (PrescriptionResult $r) => [
                'id' => $r->getId()->toRfc4122(),
                'medicineCount' => \count($r->getItems()),
                'pointsAwarded' => $r->getPointsAwarded(),
                'submittedAt' => $r->getSubmittedAt()->format(\DateTimeInterface::ATOM),
            ],
            $results,
        ));
    }

    private function countCompleted(User $decoder): int
    {
        return (int) $this->resultsQuery($decoder)
            ->select('COUNT(res.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * PrescriptionResult n'a pas de lien direct vers le déchiffreur — seul
     * PrescriptionRequest.decoder l'a (même constat déjà fait dans
     * AdminPrescriptionController sur cette relation) — d'où la jointure
     * sur la demande plutôt qu'un champ direct supposé.
     */
    private function resultsQuery(User $decoder): QueryBuilder
    {
        return $this->em->getRepository(PrescriptionResult::class)
            ->createQueryBuilder('res')
            ->innerJoin('res.request', 'req')
            ->andWhere('req.decoder = :decoder')
            ->setParameter('decoder', $decoder);
    }
}
