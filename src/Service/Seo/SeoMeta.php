<?php

declare(strict_types=1);

namespace App\Service\Seo;

/**
 * §10 du cahier des charges — "créer un système réutisable pour les metadata
 * SEO... éviter de dupliquer le SEO dans tous les controllers". Un seul DTO
 * consommé par un unique bloc Twig (templates/components/seo/head.html.twig).
 */
final class SeoMeta
{
    /**
     * @param string[] $ogImages
     * @param array<string, string> $hreflang clé = locale (fr/ar/en), valeur = URL absolue de cette variante
     * @param array<int, array<string, mixed>> $jsonLd un ou plusieurs blocs JSON-LD (Organization, LocalBusiness, BreadcrumbList, etc.)
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $canonical,
        public readonly array $hreflang = [],
        public readonly array $ogImages = [],
        public readonly array $jsonLd = [],
        public readonly bool $noindex = false,
    ) {
    }
}
