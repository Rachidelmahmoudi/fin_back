<?php

declare(strict_types=1);

namespace App\Service\Seo;

use App\Entity\Doctor;
use App\Entity\Laboratory;
use App\Entity\Medicine;
use App\Entity\Pharmacy;

/**
 * §10 : Organization, LocalBusiness/Pharmacy, Physician, MedicalLaboratory,
 * Product, Article, BreadcrumbList, FAQPage. Chaque méthode retourne un
 * tableau PHP prêt à être json_encode()é dans le <script type="application/
 * ld+json"> — jamais construit à la main dans un template Twig, pour garder
 * une seule source de vérité sur la structure de chaque schéma.
 */
final class StructuredDataService
{
    public function __construct(private readonly string $baseUrl, private readonly string $orgName = 'FinPharma')
    {
    }

    public function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $this->orgName,
            'url' => $this->baseUrl,
            'logo' => $this->baseUrl.'/assets/logo.png',
        ];
    }

    public function pharmacy(Pharmacy $pharmacy, string $url): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Pharmacy',
            'name' => $pharmacy->getName(),
            'url' => $url,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $pharmacy->getAddressRaw(),
                'addressLocality' => $pharmacy->getCity()->getName(),
                'addressCountry' => 'MA',
            ],
        ];

        if ($pharmacy->getPhone() !== null) {
            $data['telephone'] = $pharmacy->getPhone();
        }
        if ($pharmacy->getLat() !== null && $pharmacy->getLng() !== null) {
            $data['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $pharmacy->getLat(),
                'longitude' => (float) $pharmacy->getLng(),
            ];
        }
        if ($pharmacy->getPhoto() !== null) {
            $data['image'] = $pharmacy->getPhoto();
        }

        // openingHoursSpecification volontairement omis si aucune donnée
        // d'horaires n'existe encore pour cette pharmacie (§Addendum : ne
        // pas inventer de données absentes pour "faire joli" en SEO — un
        // schema incomplet mais honnête vaut mieux qu'un schema inventé
        // que Google pourrait pénaliser en cas de contrôle qualité).
        $hours = $pharmacy->getOpeningHours();
        if (\count($hours) > 0) {
            $data['openingHoursSpecification'] = array_map(
                static fn ($h) => [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => self::dayName($h->getDayOfWeek()),
                    'opens' => $h->getOpensAt()->format('H:i'),
                    'closes' => $h->getClosesAt()->format('H:i'),
                ],
                $hours->toArray(),
            );
        }

        return $data;
    }

    public function physician(Doctor $doctor, string $url): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Physician',
            'name' => $doctor->getName(),
            'url' => $url,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $doctor->getAddressRaw(),
                'addressLocality' => $doctor->getCity()->getName(),
                'addressCountry' => 'MA',
            ],
        ];

        if ($doctor->getPhone() !== null) {
            $data['telephone'] = $doctor->getPhone();
        }
        if (\count($doctor->getSpecialties()) > 0) {
            $data['medicalSpecialty'] = array_map(static fn ($s) => $s->getName(), $doctor->getSpecialties()->toArray());
        }

        return $data;
    }

    public function medicalLaboratory(Laboratory $laboratory, string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'MedicalLaboratory',
            'name' => $laboratory->getName(),
            'url' => $url,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $laboratory->getAddressRaw(),
                'addressLocality' => $laboratory->getCity()->getName(),
                'addressCountry' => 'MA',
            ],
            'telephone' => $laboratory->getPhone(),
        ];
    }

    public function product(Medicine $medicine, string $url): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $medicine->getName(),
            'url' => $url,
        ];

        if ($medicine->getManufacturer() !== null) {
            $data['brand'] = ['@type' => 'Brand', 'name' => $medicine->getManufacturer()];
        }

        $price = $medicine->getPpvCnss() ?? $medicine->getPriceCommercial();
        if ($price !== null) {
            $data['offers'] = [
                '@type' => 'Offer',
                'price' => $price,
                'priceCurrency' => 'MAD',
                'availability' => $medicine->getStatus() === 'Commercialisé'
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ];
        }

        return $data;
    }

    /**
     * @param array<int, array{name: string, url: string}> $items
     */
    public function breadcrumbList(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                static fn (array $item, int $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $item['name'],
                    'item' => $item['url'],
                ],
                $items,
                array_keys($items),
            ),
        ];
    }

    /**
     * @param array<int, array{question: string, answer: string}> $qa
     */
    public function faqPage(array $qa): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(
                static fn (array $item) => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
                ],
                $qa,
            ),
        ];
    }

    private static function dayName(int $isoDayOfWeek): string
    {
        return [
            1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
            5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
        ][$isoDayOfWeek] ?? 'Monday';
    }
}
