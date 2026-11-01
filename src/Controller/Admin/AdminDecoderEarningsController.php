<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\PrescriptionResult;
use App\Entity\User;
use App\Service\Admin\AdminSidebarCountsService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Vue d'ensemble des gains de tous les déchiffreurs — lecture seule,
 * même logique que AdminPrescriptionController (historique des
 * déchiffrements) mais agrégée par déchiffreur plutôt que listée demande
 * par demande.
 *
 * §Correctif : PostgreSQL n'a pas d'opérateur LIKE sur le type `json`
 * (contrairement à MySQL, plus permissif) — "operator does not exist:
 * json ~~ unknown". Le filtre "utilisateurs ayant ROLE_DECODER" (stocké
 * dans une colonne JSON, pas une vraie relation de rôles dans ce projet)
 * doit donc passer par du SQL natif avec un cast explicite en texte
 * (`roles::text LIKE ...`), pas par le QueryBuilder de l'ORM qui ne sait
 * pas produire ce cast tout seul — même principe déjà appliqué à
 * PharmacySearchService pour ACOS/SIN, DQL n'étant pas du SQL.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminDecoderEarningsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
        private readonly AdminSidebarCountsService $sidebarCounts,
    ) {
    }

    #[Route('/admin/decoders', name: 'admin_decoders_earnings', methods: ['GET'])]
    public function index(): Response
    {
        $ids = $this->connection->fetchFirstColumn(
            "SELECT id FROM app_user WHERE roles::text LIKE :role ORDER BY decoder_points DESC",
            ['role' => '%ROLE_DECODER%'],
        );

        // find() préserverait l'ordre naturel de l'ORM (souvent par id),
        // pas le tri par points déjà fait en SQL — on retrie donc en PHP
        // selon l'ordre exact des ids renvoyés par la requête ci-dessus.
        $decodersById = [];
        foreach ($ids as $id) {
            $decoder = $this->em->getRepository(User::class)->find($id);
            if ($decoder !== null) {
                $decodersById[$id] = $decoder;
            }
        }
        $decoders = array_values($decodersById);

        $completedCounts = [];
        foreach ($decoders as $decoder) {
            /** @var User $decoder */
            $completedCounts[$decoder->getId()->toRfc4122()] = (int) $this->em->getRepository(PrescriptionResult::class)
                ->createQueryBuilder('res')
                ->select('COUNT(res.id)')
                ->innerJoin('res.request', 'req')
                ->andWhere('req.decoder = :decoder')
                ->setParameter('decoder', $decoder)
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $this->render('admin/decoders_earnings.html.twig', [
            'decoders' => $decoders,
            'completedCounts' => $completedCounts,
            'pendingCounts' => $this->sidebarCounts->get(),
        ]);
    }
}