<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Service\Seo\SeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** §14-16 : /services, /services/trouver-un-medicament, /services/assistant-pharmaceutique. */
final class ServiceController extends AbstractController
{
    public function __construct(private readonly SeoService $seoService)
    {
    }

    #[Route('/{_locale}/services', name: 'v1_service_index', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function index(string $_locale): Response
    {
        return $this->render('v1/service/index.html.twig', [
            'seo' => $this->seoService->forSimplePage(
                $_locale,
                $_locale === 'ar' ? 'خدمات FinPharma' : 'Nos services',
                $_locale === 'ar' ? 'اكتشف خدمات FinPharma: البحث عن دواء والمساعد الصيدلي.' : 'Découvrez les services FinPharma : trouver un médicament et l\'assistant pharmaceutique.',
                'services',
            ),
        ]);
    }

    /**
     * §15 : rechercher un médicament, envoyer une ordonnance, être mis en
     * relation avec les pharmacies partenaires. Le formulaire web soumet
     * vers la même logique que côté mobile (StockItem, Phase 4e) — pas
     * encore branché ici, cette page décrit le service et capture l'intérêt
     * (email) en attendant le module de recherche+stock complet.
     */
    #[Route('/{_locale}/services/trouver-un-medicament', name: 'v1_service_find_medicine', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function findMedicine(string $_locale): Response
    {
        return $this->render('v1/service/find-medicine.html.twig', [
            'seo' => $this->seoService->forSimplePage(
                $_locale,
                $_locale === 'ar' ? 'البحث عن دواء' : 'Trouver un médicament',
                $_locale === 'ar' ? 'ابحث عن دواء لدى الصيدليات الشريكة القريبة منك.' : 'Recherchez un médicament auprès des pharmacies partenaires proches de vous.',
                'services/trouver-un-medicament',
            ),
        ]);
    }

    /**
     * §16 : déchiffrement d'ordonnance côté pharmacien/assistant. Le
     * workflow réel (publish/accept/submit) est déjà exposé en API
     * (Phase 4b) ; cette page web présente le service et redirige vers
     * l'app mobile ou un espace pharmacien connecté pour l'usage réel —
     * §16 précise explicitement que ce n'est pas un diagnostic médical,
     * rappel affiché en évidence sur la page.
     */
    #[Route('/{_locale}/services/assistant-pharmaceutique', name: 'v1_service_assistant', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function assistant(string $_locale): Response
    {
        return $this->render('v1/service/assistant.html.twig', [
            'seo' => $this->seoService->forSimplePage(
                $_locale,
                $_locale === 'ar' ? 'المساعد الصيدلي' : 'Assistant pharmaceutique',
                $_locale === 'ar' ? 'خدمة فك تشفير الوصفات الطبية للصيادلة.' : 'Service de déchiffrement d\'ordonnances pour pharmaciens et assistants.',
                'services/assistant-pharmaceutique',
            ),
        ]);
    }
}
