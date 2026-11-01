<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Entity\EstablishmentStatus;
use App\Repository\CityRepository;
use App\Repository\LaboratoryRepository;
use App\Service\Seo\SeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LaboratoryController extends AbstractController
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly LaboratoryRepository $laboratoryRepository,
        private readonly CityRepository $cityRepository,
        private readonly SeoService $seoService,
    ) {
    }

    #[Route('/{_locale}/laboratoires/{city}', name: 'v1_laboratory_list', requirements: ['_locale' => 'fr|ar'], defaults: ['city' => null], methods: ['GET'])]
    public function list(string $_locale, Request $request, ?string $city = null): Response
    {
        $cityEntity = $city !== null ? $this->cityRepository->findOneBy(['slug' => $city]) : null;
        $page = (int) $request->query->get('page', 1);

        $qb = $this->laboratoryRepository->createQueryBuilder('l')
            ->innerJoin('l.city', 'c')
            ->andWhere('l.status = :status')
            ->setParameter('status', EstablishmentStatus::PUBLISHED);

        if ($cityEntity !== null) {
            $qb->andWhere('c.id = :cityId')->setParameter('cityId', $cityEntity->getId());
        }
        if (($q = $request->query->get('q')) !== null) {
            $qb->andWhere('LOWER(l.name) LIKE :q')->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        $qb->orderBy('l.name', 'ASC')->setFirstResult(($page - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE);

        return $this->render('v1/laboratory/list.html.twig', [
            'laboratories' => $qb->getQuery()->getResult(),
            'city' => $cityEntity,
            'seo' => $this->seoService->forLaboratoryList($_locale, $cityEntity?->getName()),
        ]);
    }

    #[Route('/{_locale}/laboratoires/show/{slug}', name: 'v1_laboratory_show', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function show(string $_locale, string $slug): Response
    {
        $uid = substr($slug, -6);
        $laboratory = $this->laboratoryRepository->findOneBy(['uid' => $uid, 'status' => EstablishmentStatus::PUBLISHED]);

        if ($laboratory === null) {
            throw $this->createNotFoundException();
        }

        $nearby = $this->laboratoryRepository->createQueryBuilder('l')
            ->andWhere('l.city = :city')
            ->andWhere('l.id != :id')
            ->andWhere('l.status = :status')
            ->setParameter('city', $laboratory->getCity())
            ->setParameter('id', $laboratory->getId())
            ->setParameter('status', EstablishmentStatus::PUBLISHED)
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        return $this->render('v1/laboratory/show.html.twig', [
            'laboratory' => $laboratory,
            'nearby' => $nearby,
            'seo' => $this->seoService->forLaboratoryShow($laboratory, $_locale),
        ]);
    }
}
