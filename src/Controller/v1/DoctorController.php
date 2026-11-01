<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Entity\EstablishmentStatus;
use App\Repository\CityRepository;
use App\Repository\DoctorRepository;
use App\Repository\SpecialtyRepository;
use App\Service\Seo\SeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * §6 et §9 : /medecins, /medecins/ville/{ville}, /medecins/specialite/{specialite},
 * /medecins/specialite/{specialite}/{ville}, /medecins/{slug}-{uid}.
 */
final class DoctorController extends AbstractController
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly DoctorRepository $doctorRepository,
        private readonly CityRepository $cityRepository,
        private readonly SpecialtyRepository $specialtyRepository,
        private readonly SeoService $seoService,
    ) {
    }

    #[Route('/{_locale}/medecins', name: 'v1_doctor_list', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function list(string $_locale, Request $request): Response
    {
        return $this->renderList($_locale, $request, null, null);
    }

    #[Route('/{_locale}/medecins/ville/{city}', name: 'v1_doctor_list_by_city', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function listByCity(string $_locale, Request $request, string $city): Response
    {
        return $this->renderList($_locale, $request, $city, null);
    }

    #[Route('/{_locale}/medecins/specialite/{specialty}', name: 'v1_doctor_list_by_specialty', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function listBySpecialty(string $_locale, Request $request, string $specialty): Response
    {
        return $this->renderList($_locale, $request, null, $specialty);
    }

    #[Route('/{_locale}/medecins/specialite/{specialty}/{city}', name: 'v1_doctor_list_by_specialty_city', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function listBySpecialtyAndCity(string $_locale, Request $request, string $specialty, string $city): Response
    {
        return $this->renderList($_locale, $request, $city, $specialty);
    }

    #[Route('/{_locale}/medecins/show/{slug}', name: 'v1_doctor_show', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function show(string $_locale, string $slug): Response
    {
        $uid = substr($slug, -6);
        $doctor = $this->doctorRepository->findOneBy(['uid' => $uid, 'status' => EstablishmentStatus::PUBLISHED]);

        if ($doctor === null) {
            throw $this->createNotFoundException();
        }

        $nearby = $this->doctorRepository->createQueryBuilder('d')
            ->andWhere('d.city = :city')
            ->andWhere('d.id != :id')
            ->andWhere('d.status = :status')
            ->setParameter('city', $doctor->getCity())
            ->setParameter('id', $doctor->getId())
            ->setParameter('status', EstablishmentStatus::PUBLISHED)
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        return $this->render('v1/doctor/show.html.twig', [
            'doctor' => $doctor,
            'nearby' => $nearby,
            'seo' => $this->seoService->forDoctorShow($doctor, $_locale),
        ]);
    }

    private function renderList(string $locale, Request $request, ?string $citySlug, ?string $specialtySlug): Response
    {
        $city = $citySlug !== null ? $this->cityRepository->findOneBy(['slug' => $citySlug]) : null;
        $specialty = $specialtySlug !== null ? $this->specialtyRepository->findOneBy(['slug' => $specialtySlug]) : null;
        $page = (int) $request->query->get('page', 1);

        $qb = $this->doctorRepository->createQueryBuilder('d')
            ->innerJoin('d.city', 'c')
            ->andWhere('d.status = :status')
            ->setParameter('status', EstablishmentStatus::PUBLISHED);

        if ($city !== null) {
            $qb->andWhere('c.id = :cityId')->setParameter('cityId', $city->getId());
        }
        if ($specialty !== null) {
            $qb->innerJoin('d.specialties', 's')->andWhere('s.id = :specialtyId')->setParameter('specialtyId', $specialty->getId());
        }
        if (($q = $request->query->get('q')) !== null) {
            $qb->andWhere('LOWER(d.name) LIKE :q')->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        $qb->orderBy('d.name', 'ASC')->setFirstResult(($page - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE);
        $doctors = $qb->getQuery()->getResult();

        return $this->render('v1/doctor/list.html.twig', [
            'doctors' => $doctors,
            'city' => $city,
            'specialty' => $specialty,
            'specialties' => $this->specialtyRepository->findAll(),
            'seo' => $this->seoService->forDoctorList($locale, $city?->getName(), $specialty?->getName()),
        ]);
    }
}
