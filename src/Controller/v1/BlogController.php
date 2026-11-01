<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Service\Seo\SeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * §12 : blog. L'entité Article/BlogCategory n'a pas encore été modélisée
 * (hors périmètre demandé jusqu'ici — priorités 1-6 du cahier des charges
 * portaient sur pharmacies/médicaments/médecins/laboratoires/SEO). Cette
 * route existe dès maintenant pour que /sitemap_blog.xml et le lien menu
 * ne soient jamais des liens morts, et affiche un état "à venir" plutôt
 * qu'une 404 — un vrai contenu vaudra la peine d'une Phase dédiée avec son
 * propre modèle de données (catégories, tags, auteur, Schema.org Article).
 */
final class BlogController extends AbstractController
{
    public function __construct(private readonly SeoService $seoService)
    {
    }

    #[Route('/{_locale}/blog', name: 'v1_blog_index', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function index(string $_locale): Response
    {
        return $this->render('v1/blog/index.html.twig', [
            'seo' => $this->seoService->forSimplePage(
                $_locale,
                $_locale === 'ar' ? 'المدونة' : 'Blog',
                $_locale === 'ar' ? 'مقالات حول الصحة والأدوية في المغرب — قريباً.' : 'Articles santé, médicaments et pharmacie au Maroc — bientôt disponible.',
                'blog',
            ),
        ]);
    }
}
