<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Service\Seo\SeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages web de connexion/inscription. Contrairement à l'API (JWT
 * stateless, Phase 4b), ces pages sont de simples vues Twig — toute la
 * logique d'authentification se fait côté client via
 * public/js/api-client.js qui appelle l'API existante et stocke les tokens.
 * Pas de nouveau système d'auth web ; on réutilise celui de l'API.
 */
final class AuthPageController extends AbstractController
{
    public function __construct(private readonly SeoService $seoService)
    {
    }

    #[Route('/{_locale}/connexion', name: 'v1_login', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function login(string $_locale): Response
    {
        return $this->render('v1/auth/login.html.twig', [
            'seo' => $this->seoService->forSimplePage(
                $_locale,
                $_locale === 'ar' ? 'تسجيل الدخول' : 'Connexion',
                '',
                'connexion',
            ),
        ]);
    }

    #[Route('/{_locale}/inscription', name: 'v1_register', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function register(string $_locale): Response
    {
        return $this->render('v1/auth/register.html.twig', [
            'seo' => $this->seoService->forSimplePage(
                $_locale,
                $_locale === 'ar' ? 'إنشاء حساب' : 'Créer un compte',
                '',
                'inscription',
            ),
        ]);
    }
}
