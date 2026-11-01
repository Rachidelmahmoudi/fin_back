<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Repository\CityRepository;
use App\Repository\PharmacyRepository;
use App\Service\Search\PharmacySearchService;
use App\Service\Seo\SeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * §5, §9 : /pharmacies, /pharmacies/{ville}, /pharmacies/{slug}-{uid},
 * /pharmacies-de-garde, /pharmacies-de-garde/{ville}, idem "ouvertes".
 * Un seul controller/template pour les 3 variantes (toutes/garde/ouvertes)
 * plutôt que 3 controllers quasi identiques — la variante est un paramètre
 * de recherche, pas une structure de page différente.
 */
final class PharmacyController extends AbstractController
{
    public function __construct(
        private readonly PharmacySearchService $searchService,
        private readonly PharmacyRepository $pharmacyRepository,
        private readonly CityRepository $cityRepository,
        private readonly SeoService $seoService,
    ) {
    }

    #[Route('/{_locale}/pharmacies/{city}', name: 'v1_pharmacy_list', requirements: ['_locale' => 'fr|ar'], defaults: ['city' => null], methods: ['GET'])]
    public function list(string $_locale, Request $request, ?string $city = null): Response
    {
        return $this->renderList($_locale, $request, $city, onDuty: false, open: false);
    }

    #[Route('/{_locale}/pharmacies-de-garde/{city}', name: 'v1_pharmacy_duty_list', requirements: ['_locale' => 'fr|ar'], defaults: ['city' => null], methods: ['GET'])]
    public function dutyList(string $_locale, Request $request, ?string $city = null): Response
    {
        return $this->renderList($_locale, $request, $city, onDuty: true, open: false);
    }

    #[Route('/{_locale}/pharmacies-ouvertes/{city}', name: 'v1_pharmacy_open_list', requirements: ['_locale' => 'fr|ar'], defaults: ['city' => null], methods: ['GET'])]
    public function openList(string $_locale, Request $request, ?string $city = null): Response
    {
        return $this->renderList($_locale, $request, $city, onDuty: false, open: true);
    }

    #[Route('/{_locale}/pharmacies/show/{slug}', name: 'v1_pharmacy_show', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function show(string $_locale, string $slug): Response
    {
        // slug attendu au format {slug}-{uid} : l'uid (6 hex chars) est le
        // dernier segment après le dernier tiret — voir SlugHelper::shortUid().
        $uid = substr($slug, -6);
        $pharmacy = $this->pharmacyRepository->findOneBy(['uid' => $uid]);

        if ($pharmacy === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('v1/pharmacy/show.html.twig', [
            'pharmacy' => $pharmacy,
            'nearby' => $this->nearbyPharmacies($pharmacy),
            'seo' => $this->seoService->forPharmacyShow($pharmacy, $_locale),
        ]);
    }

    /**
     * §detail-page (patron directory) : "établissements similaires à
     * proximité" en bas de fiche — même ville, exclut la fiche courante.
     * Pas de vrai calcul de proximité géo ici (juste même ville) pour
     * rester simple ; à affiner avec un rayon réel si un jour utile.
     *
     * @return \App\Entity\Pharmacy[]
     */
    private function nearbyPharmacies(\App\Entity\Pharmacy $pharmacy): array
    {
        return $this->pharmacyRepository->createQueryBuilder('p')
            ->andWhere('p.city = :city')
            ->andWhere('p.id != :id')
            ->andWhere('p.status = :status')
            ->setParameter('city', $pharmacy->getCity())
            ->setParameter('id', $pharmacy->getId())
            ->setParameter('status', \App\Entity\PharmacyStatus::ACTIVE)
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();
    }

    private function renderList(string $locale, Request $request, ?string $citySlug, bool $onDuty, bool $open): Response
    {
        $city = $citySlug !== null ? $this->cityRepository->findOneBy(['slug' => $citySlug]) : null;

        $results = $this->searchService->search(
            city: $citySlug,
            query: $request->query->get('q'),
            onDutyOnly: $onDuty,
            openNowOnly: $open,
            page: (int) $request->query->get('page', 1),
        );

        return $this->render('v1/pharmacy/list.html.twig', [
            'pharmacies' => $results->items,
            'distances' => $results->distances,
            'totalCount' => $results->totalCount,
            'city' => $city,
            'onDuty' => $onDuty,
            'open' => $open,
            'seo' => $this->seoService->forPharmacyList($locale, $city?->getName(), $onDuty, $open),
        ]);
    }
}
