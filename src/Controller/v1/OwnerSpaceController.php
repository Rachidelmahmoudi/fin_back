<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Service\Seo\SeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Espace propriétaire côté site. Le site n'a pas de session serveur : la
 * connexion est un JWT dans le navigateur (api-client.js). Cette route
 * rend donc seulement une coquille ; les données arrivent par l'API
 * (GET /api/v1/me/establishments) et c'est l'API qui contrôle l'accès —
 * cette page ne protège rien par elle-même.
 */
final class OwnerSpaceController extends AbstractController
{
    public function __construct(private readonly SeoService $seoService)
    {
    }

    #[Route('/{_locale}/mon-espace', name: 'v1_owner_space', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function index(string $_locale): Response
    {
        return $this->render('v1/owner/index.html.twig', [
            'seo' => $this->seoService->forSimplePage(
                $_locale,
                $_locale === 'ar' ? 'مساحتي' : 'Mon espace',
                $_locale === 'ar' ? 'إدارة مؤسساتك على FinPharma.' : 'Gérez vos établissements sur FinPharma.',
                'mon-espace',
            ),
        ]);
    }
}
