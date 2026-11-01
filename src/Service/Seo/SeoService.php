<?php

declare(strict_types=1);

namespace App\Service\Seo;

use App\Entity\Doctor;
use App\Entity\Laboratory;
use App\Entity\Medicine;
use App\Entity\Pharmacy;

/**
 * §10-11 du cahier des charges. Chaque controller Web appelle UNE méthode
 * ici (ex: `forPharmacyShow($pharmacy)`) et passe le résultat au template —
 * jamais de title/meta/JSON-LD construits directement dans un controller ou
 * un template Twig.
 *
 * hreflang : seulement FR/AR pour l'instant (§Addendum 2, décision
 * confirmée — EN retiré). Les URLs sont préfixées /fr/ et /ar/, générées ici
 * à partir de la route courante plutôt que codées en dur, pour ne jamais
 * désynchroniser hreflang et le routing réel.
 */
final class SeoService
{
    public function __construct(
        private readonly StructuredDataService $structuredData,
        private readonly string $baseUrl,
    ) {
    }

    public function forHome(string $locale): SeoMeta
    {
        $title = $locale === 'ar'
            ? 'FinPharma — دليل الصيدليات والأدوية في المغرب'
            : 'FinPharma — Trouvez une pharmacie, un médecin ou un médicament au Maroc';
        $description = $locale === 'ar'
            ? 'ابحث عن أقرب صيدلية، صيدلية الحراسة، طبيب أو مختبر تحاليل طبية في المغرب.'
            : 'Trouvez rapidement une pharmacie proche, une pharmacie de garde, un médecin, un laboratoire d\'analyses ou un médicament partout au Maroc.';

        return new SeoMeta(
            title: $title,
            description: $description,
            canonical: $this->url($locale, ''),
            hreflang: $this->hreflangFor(''),
            jsonLd: [$this->structuredData->organization()],
        );
    }

    /**
     * §11 SEO local : "Pharmacie à Marrakech" etc. Titre/description
     * uniques par ville, jamais un template générique avec juste le nom de
     * la ville substitué sans autre contenu (§11 : "ne pas créer
     * automatiquement des milliers de pages pauvres en contenu").
     */
    public function forPharmacyList(string $locale, ?string $cityName = null, bool $onDutyOnly = false, bool $openNowOnly = false): SeoMeta
    {
        $suffix = $onDutyOnly ? ($locale === 'ar' ? ' الحارسة' : ' de garde') : ($openNowOnly ? ($locale === 'ar' ? ' المفتوحة' : ' ouvertes') : '');
        $place = $cityName !== null ? ' '.($locale === 'ar' ? 'في '.$cityName : 'à '.$cityName) : '';

        $title = $locale === 'ar'
            ? sprintf('صيدليات%s%s — FinPharma', $suffix, $place)
            : sprintf('Pharmacies%s%s — FinPharma', $suffix, $place);

        $description = $locale === 'ar'
            ? sprintf('اعثر على قائمة الصيدليات%s%s مع العناوين وأرقام الهاتف ومواعيد العمل.', $suffix, $place)
            : sprintf('Consultez la liste des pharmacies%s%s avec adresses, téléphones et horaires, à jour.', $suffix, $place);

        $slugPath = match (true) {
            $onDutyOnly => 'pharmacies-de-garde'.($cityName !== null ? '/'.$this->slugify($cityName) : ''),
            $openNowOnly => 'pharmacies-ouvertes'.($cityName !== null ? '/'.$this->slugify($cityName) : ''),
            default => 'pharmacies'.($cityName !== null ? '/'.$this->slugify($cityName) : ''),
        };

        return new SeoMeta(
            title: $title,
            description: $description,
            canonical: $this->url($locale, $slugPath),
            hreflang: $this->hreflangFor($slugPath),
            jsonLd: [
                $this->structuredData->breadcrumbList($this->pharmacyListBreadcrumbs($locale, $cityName, $onDutyOnly, $openNowOnly)),
            ],
        );
    }

    public function forPharmacyShow(Pharmacy $pharmacy, string $locale): SeoMeta
    {
        $slugPath = 'pharmacies/'.$pharmacy->getSlug().'-'.$pharmacy->getUid();
        $url = $this->url($locale, $slugPath);

        $title = $locale === 'ar'
            ? sprintf('%s — %s | FinPharma', $pharmacy->getName(), $pharmacy->getCity()->getName())
            : sprintf('%s à %s — Horaires, téléphone, itinéraire | FinPharma', $pharmacy->getName(), $pharmacy->getCity()->getName());

        $description = $locale === 'ar'
            ? sprintf('%s، %s. رقم الهاتف والعنوان ومواعيد العمل.', $pharmacy->getName(), $pharmacy->getAddressRaw())
            : sprintf('%s, %s. Téléphone, adresse et horaires d\'ouverture.', $pharmacy->getName(), $pharmacy->getAddressRaw());

        return new SeoMeta(
            title: $title,
            description: $description,
            canonical: $url,
            hreflang: $this->hreflangFor($slugPath),
            ogImages: $pharmacy->getPhoto() !== null ? [$pharmacy->getPhoto()] : [],
            jsonLd: [
                $this->structuredData->pharmacy($pharmacy, $url),
                $this->structuredData->breadcrumbList([
                    ['name' => $locale === 'ar' ? 'الرئيسية' : 'Accueil', 'url' => $this->url($locale, '')],
                    ['name' => $locale === 'ar' ? 'الصيدليات' : 'Pharmacies', 'url' => $this->url($locale, 'pharmacies')],
                    ['name' => $pharmacy->getName(), 'url' => $url],
                ]),
            ],
        );
    }

    /**
     * §11 : "Cardiologue à Marrakech", "Dermatologue à Casablanca". Même
     * logique de contenu unique par ville+spécialité que forPharmacyList.
     */
    public function forDoctorList(string $locale, ?string $cityName = null, ?string $specialtyName = null): SeoMeta
    {
        $place = $cityName !== null ? ' '.($locale === 'ar' ? 'في '.$cityName : 'à '.$cityName) : '';
        $subject = $specialtyName ?? ($locale === 'ar' ? 'أطباء' : 'Médecins');

        $title = sprintf('%s%s — FinPharma', $subject, $place);
        $description = $locale === 'ar'
            ? sprintf('اعثر على %s%s مع العناوين وأرقام الهاتف والتخصصات.', $subject, $place)
            : sprintf('Trouvez un %s%s : adresse, téléphone, prix de consultation.', mb_strtolower($subject), $place);

        $slugPath = 'medecins'.($specialtyName !== null ? '/specialite/'.$this->slugify($specialtyName) : '').($cityName !== null ? '/'.$this->slugify($cityName) : '');

        return new SeoMeta(
            title: $title,
            description: $description,
            canonical: $this->url($locale, $slugPath),
            hreflang: $this->hreflangFor($slugPath),
            jsonLd: [$this->structuredData->breadcrumbList([
                ['name' => $locale === 'ar' ? 'الرئيسية' : 'Accueil', 'url' => $this->url($locale, '')],
                ['name' => $subject, 'url' => $this->url($locale, $slugPath)],
            ])],
        );
    }

    public function forDoctorShow(Doctor $doctor, string $locale): SeoMeta
    {
        $slugPath = 'medecins/'.$doctor->getSlug().'-'.$doctor->getUid();
        $url = $this->url($locale, $slugPath);
        $specialty = $doctor->getSpecialties()->first() ?: null;

        $title = $locale === 'ar'
            ? sprintf('%s — %s | FinPharma', $doctor->getName(), $doctor->getCity()->getName())
            : sprintf('%s%s à %s | FinPharma', $doctor->getName(), $specialty ? ' — '.$specialty->getName() : '', $doctor->getCity()->getName());

        $description = $locale === 'ar'
            ? sprintf('%s، %s.', $doctor->getName(), $doctor->getAddressRaw())
            : sprintf('%s, %s. Téléphone, adresse et prise de rendez-vous.', $doctor->getName(), $doctor->getAddressRaw());

        return new SeoMeta(
            title: $title,
            description: $description,
            canonical: $url,
            hreflang: $this->hreflangFor($slugPath),
            jsonLd: [
                $this->structuredData->physician($doctor, $url),
                $this->structuredData->breadcrumbList([
                    ['name' => $locale === 'ar' ? 'الرئيسية' : 'Accueil', 'url' => $this->url($locale, '')],
                    ['name' => $locale === 'ar' ? 'الأطباء' : 'Médecins', 'url' => $this->url($locale, 'medecins')],
                    ['name' => $doctor->getName(), 'url' => $url],
                ]),
            ],
        );
    }

    public function forLaboratoryList(string $locale, ?string $cityName = null): SeoMeta
    {
        $place = $cityName !== null ? ' '.($locale === 'ar' ? 'في '.$cityName : 'à '.$cityName) : '';
        $title = $locale === 'ar' ? sprintf('مختبرات التحاليل%s — FinPharma', $place) : sprintf('Laboratoires d\'analyses%s — FinPharma', $place);
        $description = $locale === 'ar'
            ? sprintf('اعثر على مختبر تحاليل طبية%s مع الأسعار والعناوين.', $place)
            : sprintf('Trouvez un laboratoire d\'analyses médicales%s : analyses proposées, prix, adresse.', $place);

        $slugPath = 'laboratoires'.($cityName !== null ? '/'.$this->slugify($cityName) : '');

        return new SeoMeta(
            title: $title,
            description: $description,
            canonical: $this->url($locale, $slugPath),
            hreflang: $this->hreflangFor($slugPath),
        );
    }

    public function forLaboratoryShow(Laboratory $laboratory, string $locale): SeoMeta
    {
        $slugPath = 'laboratoires/'.$laboratory->getSlug().'-'.$laboratory->getUid();
        $url = $this->url($locale, $slugPath);

        $title = $locale === 'ar'
            ? sprintf('%s — %s | FinPharma', $laboratory->getName(), $laboratory->getCity()->getName())
            : sprintf('%s à %s — Analyses et prix | FinPharma', $laboratory->getName(), $laboratory->getCity()->getName());

        $description = $locale === 'ar'
            ? sprintf('%s، %s.', $laboratory->getName(), $laboratory->getAddressRaw())
            : sprintf('%s, %s. Analyses proposées, prix, téléphone.', $laboratory->getName(), $laboratory->getAddressRaw());

        return new SeoMeta(
            title: $title,
            description: $description,
            canonical: $url,
            hreflang: $this->hreflangFor($slugPath),
            jsonLd: [
                $this->structuredData->medicalLaboratory($laboratory, $url),
                $this->structuredData->breadcrumbList([
                    ['name' => $locale === 'ar' ? 'الرئيسية' : 'Accueil', 'url' => $this->url($locale, '')],
                    ['name' => $locale === 'ar' ? 'المختبرات' : 'Laboratoires', 'url' => $this->url($locale, 'laboratoires')],
                    ['name' => $laboratory->getName(), 'url' => $url],
                ]),
            ],
        );
    }

    public function forMedicineList(string $locale): SeoMeta
    {
        $slugPath = 'medicaments';

        return new SeoMeta(
            title: $locale === 'ar' ? 'الأدوية — FinPharma' : 'Médicaments — Prix, DCI, remboursement CNSS | FinPharma',
            description: $locale === 'ar'
                ? 'ابحث عن دواء واطلع على السعر والتركيبة والتعويض من الصندوق الوطني للضمان الاجتماعي.'
                : 'Recherchez un médicament par nom ou DCI : prix, forme, remboursement CNSS.',
            canonical: $this->url($locale, $slugPath),
            hreflang: $this->hreflangFor($slugPath),
        );
    }

    public function forMedicineShow(Medicine $medicine, string $locale): SeoMeta
    {
        $slugPath = 'medicaments/'.$medicine->getSlug().'-'.$medicine->getUid();
        $url = $this->url($locale, $slugPath);

        $title = $locale === 'ar'
            ? sprintf('%s — السعر والتركيبة | FinPharma', $medicine->getName())
            : sprintf('%s — Prix, DCI, remboursement | FinPharma', $medicine->getName());

        $description = $locale === 'ar'
            ? sprintf('%s: السعر، الجرعة، التعويض من الضمان الاجتماعي.', $medicine->getName())
            : sprintf('%s : prix, dosage, présentation, remboursement CNSS et indications.', $medicine->getName());

        return new SeoMeta(
            title: $title,
            description: $description,
            canonical: $url,
            hreflang: $this->hreflangFor($slugPath),
            jsonLd: [
                $this->structuredData->product($medicine, $url),
                $this->structuredData->breadcrumbList([
                    ['name' => $locale === 'ar' ? 'الرئيسية' : 'Accueil', 'url' => $this->url($locale, '')],
                    ['name' => $locale === 'ar' ? 'الأدوية' : 'Médicaments', 'url' => $this->url($locale, 'medicaments')],
                    ['name' => $medicine->getName(), 'url' => $url],
                ]),
            ],
        );
    }

    /**
     * Fallback générique pour les pages sans logique SEO spécifique
     * (services, contact, choix d'établissement) — un seul point d'entrée
     * plutôt qu'une méthode dédiée par page statique, pour éviter la
     * prolifération de méthodes quasi identiques.
     */
    public function forSimplePage(string $locale, string $title, string $description, string $slugPath): SeoMeta
    {
        return new SeoMeta(
            title: $title.' — FinPharma',
            description: $description,
            canonical: $this->url($locale, $slugPath),
            hreflang: $this->hreflangFor($slugPath),
        );
    }

    /**
     * @return array<int, array{name: string, url: string}>
     */
    private function pharmacyListBreadcrumbs(string $locale, ?string $cityName, bool $onDutyOnly, bool $openNowOnly): array
    {
        $home = ['name' => $locale === 'ar' ? 'الرئيسية' : 'Accueil', 'url' => $this->url($locale, '')];
        $label = $onDutyOnly
            ? ($locale === 'ar' ? 'صيدليات الحراسة' : 'Pharmacies de garde')
            : ($openNowOnly ? ($locale === 'ar' ? 'صيدليات مفتوحة' : 'Pharmacies ouvertes') : ($locale === 'ar' ? 'الصيدليات' : 'Pharmacies'));

        $items = [$home, ['name' => $label, 'url' => $this->url($locale, 'pharmacies')]];
        if ($cityName !== null) {
            $items[] = ['name' => $cityName, 'url' => $this->url($locale, 'pharmacies/'.$this->slugify($cityName))];
        }

        return $items;
    }

    /** @return array<string, string> */
    private function hreflangFor(string $slugPath): array
    {
        return [
            'fr' => $this->url('fr', $slugPath),
            'ar' => $this->url('ar', $slugPath),
        ];
    }

    private function url(string $locale, string $slugPath): string
    {
        $path = trim($slugPath, '/');

        return rtrim($this->baseUrl, '/').'/'.$locale.($path !== '' ? '/'.$path : '');
    }

    private function slugify(string $text): string
    {
        return \App\Service\Import\SlugHelper::slugify($text);
    }
}
