<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Service\Seo\SeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Landing « Espace pro » : présente le partenariat aux pharmaciens,
 * médecins et laboratoires, puis renvoie vers le tunnel existant
 * /ajouter-mon-etablissement (création/revendication de fiche).
 */
final class ProController extends AbstractController
{
    public function __construct(private readonly SeoService $seoService)
    {
    }

    #[Route('/{_locale}/espace-pro', name: 'v1_pro_landing', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function index(string $_locale): Response
    {
        return $this->render('v1/pro/index.html.twig', [
            'seo' => $this->seoService->forSimplePage(
                $_locale,
                $_locale === 'ar' ? 'فضاء المهنيين' : 'Espace pro : pharmaciens, médecins, laboratoires',
                $_locale === 'ar'
                    ? 'انضم إلى FinPharma مجاناً: صيدلية، طبيب أو مختبر. اجعل مؤسستك مرئية لآلاف المرضى في المغرب.'
                    : 'Rejoignez FinPharma gratuitement : pharmacie, cabinet ou laboratoire. Soyez visible auprès de milliers de patients au Maroc.',
                'espace-pro',
            ),
        ]);
    }
}
