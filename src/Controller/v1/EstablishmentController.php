<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Repository\CityRepository;
use App\Repository\SpecialtyRepository;
use App\Service\Seo\SeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * §13 : /ajouter-mon-etablissement. Pages web pures (choix + formulaires) ;
 * la soumission réelle POST vers l'API existante (EstablishmentController
 * API, PharmacyController API — Phase 4c), ce controller ne fait que
 * rendre les formulaires et fournir les données de référence (villes,
 * spécialités) nécessaires à leurs selects.
 */
final class EstablishmentController extends AbstractController
{
    public function __construct(
        private readonly SeoService $seoService,
        private readonly CityRepository $cityRepository,
        private readonly SpecialtyRepository $specialtyRepository,
    ) {
    }

    #[Route('/{_locale}/ajouter-mon-etablissement', name: 'v1_establishment_choose', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function choose(string $_locale): Response
    {
        return $this->render('v1/establishment/choose.html.twig', [
            'seo' => $this->seoService->forSimplePage(
                $_locale,
                $_locale === 'ar' ? 'أضف مؤسستك' : 'Ajouter mon établissement',
                $_locale === 'ar' ? 'أضف صيدليتك أو عيادتك أو مختبرك مجاناً على FinPharma.' : 'Ajoutez gratuitement votre pharmacie, cabinet ou laboratoire sur FinPharma.',
                'ajouter-mon-etablissement',
            ),
        ]);
    }

    #[Route('/{_locale}/ajouter-mon-etablissement/pharmacie', name: 'v1_establishment_create_pharmacy', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function createPharmacy(string $_locale): Response
    {
        return $this->render('v1/establishment/create.html.twig', [
            'type' => 'pharmacy',
            'cities' => $this->cityRepository->findBy(['isActive' => true], ['name' => 'ASC']),
            'seo' => $this->seoService->forSimplePage($_locale, $_locale === 'ar' ? 'أضف صيدليتك' : 'Ajouter ma pharmacie', '', 'ajouter-mon-etablissement/pharmacie'),
        ]);
    }

    #[Route('/{_locale}/ajouter-mon-etablissement/cabinet', name: 'v1_establishment_create_doctor', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function createDoctor(string $_locale): Response
    {
        return $this->render('v1/establishment/create.html.twig', [
            'type' => 'doctor',
            'cities' => $this->cityRepository->findBy(['isActive' => true], ['name' => 'ASC']),
            'specialties' => $this->specialtyRepository->findAll(),
            'seo' => $this->seoService->forSimplePage($_locale, $_locale === 'ar' ? 'أضف عيادتك' : 'Ajouter mon cabinet', '', 'ajouter-mon-etablissement/cabinet'),
        ]);
    }

    #[Route('/{_locale}/ajouter-mon-etablissement/laboratoire', name: 'v1_establishment_create_laboratory', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function createLaboratory(string $_locale): Response
    {
        return $this->render('v1/establishment/create.html.twig', [
            'type' => 'laboratory',
            'cities' => $this->cityRepository->findBy(['isActive' => true], ['name' => 'ASC']),
            'seo' => $this->seoService->forSimplePage($_locale, $_locale === 'ar' ? 'أضف مختبرك' : 'Ajouter mon laboratoire', '', 'ajouter-mon-etablissement/laboratoire'),
        ]);
    }
}
