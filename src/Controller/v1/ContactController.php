<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Service\Seo\SeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(private readonly SeoService $seoService)
    {
    }

    #[Route('/{_locale}/contact', name: 'v1_contact', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function index(string $_locale): Response
    {
        return $this->render('v1/contact/index.html.twig', [
            'seo' => $this->seoService->forSimplePage(
                $_locale,
                $_locale === 'ar' ? 'اتصل بنا' : 'Contact',
                $_locale === 'ar' ? 'تواصل مع فريق FinPharma.' : 'Contactez l\'équipe FinPharma.',
                'contact',
            ),
        ]);
    }
}
