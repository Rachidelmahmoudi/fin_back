<?php

declare(strict_types=1);

namespace App\Controller\v1;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RobotsController extends AbstractController
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    #[Route('/robots.txt', name: 'robots_txt', methods: ['GET'])]
    public function __invoke(): Response
    {
        $sitemapUrl = $this->urlGenerator->generate('sitemap_index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $content = <<<TXT
            User-agent: *
            Allow: /
            Disallow: /api/
            Disallow: /admin/
            Disallow: /*?*sort=
            Disallow: /*?*page=

            Sitemap: {$sitemapUrl}
            TXT;

        return new Response($content, 200, ['Content-Type' => 'text/plain']);
    }
}
