<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Entity\City;
use App\Entity\Pharmacy;
use App\Entity\PharmacyStatus;
use App\Repository\CityRepository;
use App\Repository\PharmacyRepository;
use App\Repository\SpecialtyRepository;
use App\Service\Seo\SeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    /** Nombre de villes affichées dans le bloc "Rechercher par ville" (patron findoctor : liste courte + "Plus..."). */
    private const CITY_BROWSE_LIMIT = 9;

    public function __construct(
        private readonly SeoService $seoService,
        private readonly CityRepository $cityRepository,
        private readonly PharmacyRepository $pharmacyRepository,
        private readonly SpecialtyRepository $specialtyRepository,
    ) {
    }

    #[Route('/{_locale}/', name: 'v1_home', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function index(string $_locale): Response
    {
        return $this->render('v1/home/index.html.twig', [
            'seo' => $this->seoService->forHome($_locale),
            'citiesWithCount' => $this->citiesWithPharmacyCount(),
            'specialties' => $this->specialtyRepository->findAll(),
        ]);
    }

    /**
     * §11 SEO local, patron "Search by City" de findoctor : chaque ville
     * pointe vers /pharmacies/{ville} avec un compteur réel — jamais un
     * chiffre inventé, une ville sans pharmacie n'apparaît simplement pas
     * dans ce bloc (cohérent avec §11 : pas de pages pauvres mises en avant).
     *
     * §Correctif : requête reconstruite pour être ROOTÉE sur City (pas
     * Pharmacy) — Doctrine DQL exige que l'alias racine (celui du FROM)
     * soit présent dans le SELECT dès qu'on hydrate des objets entité
     * ("Cannot select entity through identification variables without
     * choosing at least one root entity alias"). La version précédente
     * partait de Pharmacy (alias p) mais ne sélectionnait que City (alias
     * c, joint) sans jamais inclure p — invalide. Partir de City résout
     * le problème nativement puisque c'est justement l'entité qu'on veut
     * récupérer comme objets.
     *
     * @return array<int, array{city: City, count: int}>
     */
    private function citiesWithPharmacyCount(): array
    {
        $rows = $this->cityRepository->createQueryBuilder('c')
            ->select('c AS city', 'COUNT(p.id) AS count')
            ->leftJoin(Pharmacy::class, 'p', 'WITH', 'p.city = c AND p.status = :status')
            ->setParameter('status', PharmacyStatus::ACTIVE)
            ->groupBy('c.id')
            ->having('COUNT(p.id) > 0')
            ->orderBy('count', 'DESC')
            ->setMaxResults(self::CITY_BROWSE_LIMIT)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row) => ['city' => $row['city'], 'count' => (int) $row['count']],
            $rows,
        );
    }
}