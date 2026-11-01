<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Entity\City;
use App\Entity\EstablishmentStatus;
use App\Entity\Medicine;
use App\Entity\Pharmacy;
use App\Entity\PharmacyStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * §33 : sitemap index + un sitemap par type de contenu. Seules les entités
 * PUBLISHED/ACTIVE et disposant d'un minimum de contenu réel sont incluses
 * (§11 : pas de pages pauvres indexées).
 */
final class SitemapController extends AbstractController
{
    private const LOCALES = ['fr', 'ar'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/sitemap.xml', name: 'sitemap_index', methods: ['GET'])]
    public function index(): Response
    {
        $sitemaps = ['pharmacies', 'medicines', 'doctors', 'laboratories', 'blog'];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($sitemaps as $name) {
            $url = $this->urlGenerator->generate('sitemap_'.$name, [], UrlGeneratorInterface::ABSOLUTE_URL);
            $xml .= sprintf("  <sitemap><loc>%s</loc></sitemap>\n", htmlspecialchars($url));
        }
        $xml .= '</sitemapindex>';

        return new Response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    #[Route('/sitemap_pharmacies.xml', name: 'sitemap_pharmacies', methods: ['GET'])]
    public function pharmacies(): Response
    {
        $pharmacies = $this->em->getRepository(Pharmacy::class)->findBy(['status' => PharmacyStatus::ACTIVE]);

        $urls = [];
        foreach ($pharmacies as $pharmacy) {
            foreach (self::LOCALES as $locale) {
                $urls[] = [
                    'loc' => $this->urlGenerator->generate('v1_pharmacy_show', [
                        '_locale' => $locale,
                        'slug' => $pharmacy->getSlug().'-'.$pharmacy->getUid(),
                    ], UrlGeneratorInterface::ABSOLUTE_URL),
                    'lastmod' => $pharmacy->getUpdatedAt()->format('Y-m-d'),
                ];
            }
        }

        return $this->urlSet($urls);
    }

    #[Route('/sitemap_medicines.xml', name: 'sitemap_medicines', methods: ['GET'])]
    public function medicines(): Response
    {
        // needsReview=true exclu volontairement (§Addendum §B) : une fiche
        // médicament dont le rapprochement de sources est incertain ne doit
        // pas être indexée tant qu'elle n'a pas été validée manuellement.
        $medicines = $this->em->getRepository(Medicine::class)->createQueryBuilder('m')
            ->andWhere('m.needsReview = false')
            ->getQuery()->getResult();

        $urls = [];
        foreach ($medicines as $medicine) {
            foreach (self::LOCALES as $locale) {
                $urls[] = [
                    'loc' => $this->urlGenerator->generate('v1_medicine_show', [
                        '_locale' => $locale,
                        'slug' => $medicine->getSlug().'-'.$medicine->getUid(),
                    ], UrlGeneratorInterface::ABSOLUTE_URL),
                    'lastmod' => $medicine->getUpdatedAt()->format('Y-m-d'),
                ];
            }
        }

        return $this->urlSet($urls);
    }

    #[Route('/sitemap_doctors.xml', name: 'sitemap_doctors', methods: ['GET'])]
    public function doctors(): Response
    {
        $doctors = $this->em->getRepository(\App\Entity\Doctor::class)->findBy(['status' => EstablishmentStatus::PUBLISHED]);

        $urls = [];
        foreach ($doctors as $doctor) {
            foreach (self::LOCALES as $locale) {
                $urls[] = [
                    'loc' => $this->urlGenerator->generate('v1_doctor_show', [
                        '_locale' => $locale,
                        'slug' => $doctor->getSlug().'-'.$doctor->getUid(),
                    ], UrlGeneratorInterface::ABSOLUTE_URL),
                    'lastmod' => $doctor->getUpdatedAt()->format('Y-m-d'),
                ];
            }
        }

        return $this->urlSet($urls);
    }

    #[Route('/sitemap_laboratories.xml', name: 'sitemap_laboratories', methods: ['GET'])]
    public function laboratories(): Response
    {
        $laboratories = $this->em->getRepository(\App\Entity\Laboratory::class)->findBy(['status' => EstablishmentStatus::PUBLISHED]);

        $urls = [];
        foreach ($laboratories as $laboratory) {
            foreach (self::LOCALES as $locale) {
                $urls[] = [
                    'loc' => $this->urlGenerator->generate('v1_laboratory_show', [
                        '_locale' => $locale,
                        'slug' => $laboratory->getSlug().'-'.$laboratory->getUid(),
                    ], UrlGeneratorInterface::ABSOLUTE_URL),
                    'lastmod' => $laboratory->getUpdatedAt()->format('Y-m-d'),
                ];
            }
        }

        return $this->urlSet($urls);
    }

    /**
     * Blog pas encore modélisé (Phase 4d/e) — sitemap vide mais route
     * existante dès maintenant, pour que l'index sitemap.xml n'ait jamais
     * de lien mort le temps que l'entité Article soit construite.
     */
    #[Route('/sitemap_blog.xml', name: 'sitemap_blog', methods: ['GET'])]
    public function blog(): Response
    {
        return $this->urlSet([]);
    }

    /** @param array<int, array{loc: string, lastmod: string}> $urls */
    private function urlSet(array $urls): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= sprintf(
                "  <url><loc>%s</loc><lastmod>%s</lastmod></url>\n",
                htmlspecialchars($u['loc']),
                $u['lastmod'],
            );
        }
        $xml .= '</urlset>';

        return new Response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
