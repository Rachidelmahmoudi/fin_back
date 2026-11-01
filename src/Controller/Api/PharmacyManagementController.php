<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Pharmacy;
use App\Entity\PharmacyOpeningHour;
use App\Entity\User;
use App\Security\Voter\PharmacyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * §22 du cahier des charges — "Gestion de pharmacie". Manquait jusqu'ici :
 * aucun endpoint ne permettait à un pharmacien de retrouver SES pharmacies
 * (seul un PATCH par id existait, Phase 4b, en supposant que le client
 * connaissait déjà l'id). Découvert en construisant l'écran mobile
 * "Ma pharmacie", qui a justement besoin de cette liste au premier
 * chargement.
 *
 * Les horaires (PharmacyOpeningHour) n'étaient pas non plus exposés côté
 * API — seulement lus en interne par PharmacyAvailabilityService (Phase
 * 4b). Ce controller ajoute le CRUD minimal nécessaire à la gestion par
 * le pharmacien, protégé par le même PharmacyVoter que le PATCH existant.
 */
#[IsGranted('ROLE_PHARMACIST')]
final class PharmacyManagementController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** §22 : liste des pharmacies possédées par l'utilisateur connecté — point d'entrée de l'espace "Ma pharmacie". */
    #[Route('/api/v1/me/pharmacies', name: 'api_me_pharmacies', methods: ['GET'])]
    public function myPharmacies(#[CurrentUser] User $user): JsonResponse
    {
        $pharmacies = $this->em->getRepository(Pharmacy::class)->findBy(['owner' => $user]);

        return new JsonResponse(array_map(
            static fn (Pharmacy $p) => [
                'id' => $p->getId()->toRfc4122(),
                'name' => $p->getName(),
                'slug' => $p->getSlug().'-'.$p->getUid(),
                'status' => $p->getStatus()->value,
                'cityName' => $p->getCity()->getName(),
                'photoUrl' => $p->getPhoto(),
            ],
            $pharmacies,
        ));
    }

    #[Route('/api/v1/pharmacies/{id}/opening-hours', name: 'api_pharmacy_opening_hours_list', methods: ['GET'])]
    public function listOpeningHours(string $id): JsonResponse
    {
        $pharmacy = $this->em->getRepository(Pharmacy::class)->find($id)
            ?? throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(PharmacyVoter::EDIT, $pharmacy);

        return new JsonResponse(array_map(
            static fn (PharmacyOpeningHour $h) => [
                'id' => $h->getId()->toRfc4122(),
                'dayOfWeek' => $h->getDayOfWeek(),
                'opensAt' => $h->getOpensAt()->format('H:i'),
                'closesAt' => $h->getClosesAt()->format('H:i'),
            ],
            $pharmacy->getOpeningHours()->toArray(),
        ));
    }

    /**
     * Remplace l'intégralité des horaires en un seul appel plutôt qu'un
     * CRUD ligne par ligne — plus simple côté client mobile (l'écran
     * "horaires" édite les 7 jours d'un coup, cf. README_MOBILE.md) et
     * évite les états intermédiaires incohérents (ex: 3 jours envoyés,
     * la requête échoue sur le 4e, la pharmacie se retrouve avec des
     * horaires à moitié mis à jour).
     */
    #[Route('/api/v1/pharmacies/{id}/opening-hours', name: 'api_pharmacy_opening_hours_replace', methods: ['PUT'])]
    public function replaceOpeningHours(string $id, Request $request): JsonResponse
    {
        $pharmacy = $this->em->getRepository(Pharmacy::class)->find($id)
            ?? throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(PharmacyVoter::EDIT, $pharmacy);

        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $hours = $payload['hours'] ?? [];

        if (!\is_array($hours)) {
            return new JsonResponse(['error' => 'hours doit être un tableau.'], 422);
        }

        foreach ($pharmacy->getOpeningHours() as $existing) {
            $this->em->remove($existing);
        }
        $pharmacy->getOpeningHours()->clear();

        foreach ($hours as $entry) {
            if (!isset($entry['dayOfWeek'], $entry['opensAt'], $entry['closesAt'])) {
                continue;
            }
            $dayOfWeek = (int) $entry['dayOfWeek'];
            if ($dayOfWeek < 1 || $dayOfWeek > 7) {
                return new JsonResponse(['error' => 'dayOfWeek doit être entre 1 (lundi) et 7 (dimanche).'], 422);
            }

            $opensAt = \DateTimeImmutable::createFromFormat('H:i', (string) $entry['opensAt']);
            $closesAt = \DateTimeImmutable::createFromFormat('H:i', (string) $entry['closesAt']);
            if ($opensAt === false || $closesAt === false) {
                return new JsonResponse(['error' => 'opensAt/closesAt doivent être au format H:i (ex: 08:30).'], 422);
            }

            $hour = new PharmacyOpeningHour($pharmacy, $dayOfWeek, $opensAt, $closesAt);
            $pharmacy->addOpeningHour($hour);
            $this->em->persist($hour);
        }

        $this->em->persist($pharmacy);
        $this->em->flush();

        return new JsonResponse(['status' => 'updated', 'count' => \count($pharmacy->getOpeningHours())]);
    }
}
