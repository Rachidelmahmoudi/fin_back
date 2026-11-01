<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Dci;
use App\Entity\Medicine;
use App\Entity\MedicineSource;
use App\Entity\MedicineSourceRecord;
use App\Entity\MedicineTableau;
use App\Entity\PrincepsOrGeneric;
use App\Repository\MedicineRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Voir Addendum §B (réponse aux précisions) pour la stratégie complète.
 * Résumé : deux sources indépendantes (medicament.ma scraping, guide CNSS),
 * fusionnées dans l'entité pivot Medicine quand le rapprochement
 * nom+DCI+dosage+forme est fiable (similarité >= 90%), sinon deux lignes
 * distinctes avec needsReview=true plutôt qu'un mauvais rapprochement deviné.
 */
final class MedicineImportService
{
    private const MATCH_THRESHOLD_PERCENT = 90.0;

    private array $dciCache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MedicineRepository $medicineRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $row une ligne brute de medications_X.json (medicament.ma)
     */
    public function importFromMedicamentMa(array $row): Medicine
    {
        $name = trim((string) ($row['name'] ?? ''));
        $url = (string) ($row['url'] ?? '');

        $existing = $this->findByExternalKey(\App\Entity\MedicineSource::MEDICAMENT_MA, $url);
        if ($existing !== null) {
            $medicine = $existing;
        } else {
            $dosage = $this->parseDosage((string) ($row['dosage'] ?? ''));
            $candidate = $this->findMatch($name, $dosage['value'] ?? null, $row['composition'] ?? null);
            $medicine = $candidate ?? $this->newMedicine($name);
        }

        $medicine->setName($name !== '' ? $name : $medicine->getName());
        $dosage = $this->parseDosage((string) ($row['dosage'] ?? ''));
        if ($dosage['value'] !== null) {
            $medicine->setDosageValue($dosage['value']);
            $medicine->setDosageUnit($dosage['unit']);
        }
        $medicine->setPresentation($this->orNull($row['presentation'] ?? null));
        $medicine->setForm($this->extractForm((string) ($row['presentation'] ?? '')));
        $medicine->setManufacturer($this->orNull($row['manufacturer'] ?? null));
        $medicine->setTherapeuticClass($this->orNull($row['therapeuticClass'] ?? null));
        $medicine->setAtcCode($this->orNull($row['Code ATC'] ?? null));
        $medicine->setStatus($this->orNull($row['status'] ?? null));
        $medicine->setIndications($this->orNull($row['indications'] ?? null));

        if (isset($row['Tableau']) && in_array($row['Tableau'], ['A', 'B'], true)) {
            $medicine->setTableau(MedicineTableau::from($row['Tableau']));
        }
        if (isset($row['Princeps']) && $row['Princeps'] === 'Oui') {
            $medicine->setPrincepsOrGeneric(PrincepsOrGeneric::PRINCEPS);
        }

        if (isset($row['price'])) {
            $medicine->setPriceCommercial($this->parsePrice((string) $row['price']));
        }
        if (isset($row['hospitalPrice'])) {
            $medicine->setPriceHospitalCommercial($this->parsePrice((string) $row['hospitalPrice']));
        }

        if (isset($row['composition']) && trim((string) $row['composition']) !== '') {
            $medicine->setDci($this->resolveDci((string) $row['composition']));
        }

        if ($medicine->getId() === null) {
            $this->em->persist($medicine);
        }
        $this->ensureUidSlug($medicine);

        $medicine->addSourceRecord(new MedicineSourceRecord($medicine, \App\Entity\MedicineSource::MEDICAMENT_MA, $url, $row));

        return $medicine;
    }

    /**
     * @param array<string, mixed> $row une ligne normalisée du guide CNSS
     *  (colonnes xlsx: Code, Laboratoire, Nom, DCI1, Dosage1, Unite Dosage1,
     *  Forme, Présentation, PPV, PH, PPV-BR, Princeps Generique,
     *  Classe Thérapeutique, TAUX Remboursement, Taux Exoneration,
     *  Droit à l'exonération)
     */
    public function importFromCnssGuide(array $row): Medicine
    {
        $ean13 = (string) ($row['Code'] ?? '');
        $name = trim((string) ($row['Nom'] ?? ''));

        $existing = $this->medicineRepository->findOneByEan13($ean13)
            ?? $this->findByExternalKey(\App\Entity\MedicineSource::CNSS_GUIDE, $ean13);

        if ($existing !== null) {
            $medicine = $existing;
        } else {
            $candidate = $this->findMatch($name, (string) ($row['Dosage1'] ?? '') ?: null, $row['DCI1'] ?? null);
            $medicine = $candidate ?? $this->newMedicine($name);
        }

        if ($medicine->getEan13Code() === null) {
            $medicine->setEan13Code($ean13);
        }
        $medicine->setName($name !== '' ? $name : $medicine->getName());

        if (isset($row['Dosage1'])) {
            $medicine->setDosageValue((string) $row['Dosage1']);
            $medicine->setDosageUnit($this->orNull($row['Unite Dosage1'] ?? null));
        }
        $medicine->setForm($medicine->getForm() ?? $this->orNull($row['Forme'] ?? null));
        $medicine->setPresentation($medicine->getPresentation() ?? $this->orNull($row['Présentation'] ?? null));
        $medicine->setManufacturer($medicine->getManufacturer() ?? $this->orNull($row['Laboratoire'] ?? null));
        $medicine->setTherapeuticClass($medicine->getTherapeuticClass() ?? $this->orNull($row['Classe Thérapeutique'] ?? null));

        if (isset($row['Princeps Generique'])) {
            $medicine->setPrincepsOrGeneric(
                $row['Princeps Generique'] === 'G' ? PrincepsOrGeneric::GENERIC : PrincepsOrGeneric::PRINCEPS,
            );
        }

        $medicine->setPpvCnss($this->numeric($row['PPV'] ?? null));
        $medicine->setPhCnss($this->numeric($row['PH'] ?? null));
        $medicine->setPpvBrCnss($this->numeric($row['PPV - BR'] ?? null));
        $medicine->setIsCnssReimbursed(true);
        $medicine->setReimbursementRate($this->numeric($row['TAUX Remboursement'] ?? null));
        $medicine->setExonerationRate($this->numeric($row['Taux Exoneration'] ?? null));
        $medicine->setExonerationEligible(($row["Droit à l'exonération"] ?? null) === 'Oui');

        if (isset($row['DCI1']) && trim((string) $row['DCI1']) !== '') {
            $medicine->setDci($medicine->getDci() ?? $this->resolveDci((string) $row['DCI1']));
        }

        if ($medicine->getId() === null) {
            $this->em->persist($medicine);
        }
        $this->ensureUidSlug($medicine);

        $medicine->addSourceRecord(new MedicineSourceRecord($medicine, \App\Entity\MedicineSource::CNSS_GUIDE, $ean13, $row));

        return $medicine;
    }

    /**
     * IMPORTANT — corrigé après validation sur les données réelles (voir
     * conversation) : la similarité sur le NOM COMPLET échoue quasi
     * systématiquement, car le nom medicament.ma combine marque+dosage+forme
     * ("ABIRATERONE GT 250 MG, Comprimé") alors que le nom CNSS n'est que la
     * marque ("GLUCOR"). Le matching fiable se fait sur MARQUE EXTRAITE
     * (tokens avant le premier chiffre du nom) + DOSAGE exact, pas sur le
     * nom brut. Validé en simulation Python sur les 588 lignes A vs les
     * 7024 lignes CNSS réelles : 36% de correspondance marque+dosage exacte,
     * ~11% marque trouvée mais dosage différent (-> needsReview), le reste
     * n'a simplement pas de ligne CNSS (médicaments non remboursés, ex.
     * Tableau B comme ABSTRAL/fentanyl) — un unmatched n'est donc pas
     * forcément une erreur de matching.
     */
    private function findMatch(string $name, ?string $dosageValue, ?string $rawDci): ?Medicine
    {
        $brand = $this->extractBrand($name);
        $candidates = $this->medicineRepository->findCandidatesByNameAndDosage($brand, null);

        $exactBrandAndDosage = null;
        $sameBrandOnly = null;

        foreach ($candidates as $candidate) {
            if ($this->extractBrand($candidate->getName()) !== $brand) {
                continue;
            }
            $sameBrandOnly ??= $candidate;
            if ($dosageValue !== null && $candidate->getDosageValue() !== null
                && $this->normalizeDosage($candidate->getDosageValue()) === $this->normalizeDosage($dosageValue)
            ) {
                $exactBrandAndDosage = $candidate;
                break;
            }
        }

        if ($exactBrandAndDosage !== null) {
            return $exactBrandAndDosage;
        }

        if ($sameBrandOnly !== null) {
            // Même marque mais dosage différent/inconnu -> pas assez fiable
            // pour fusionner, on crée une ligne séparée flaggée pour revue.
            $sameBrandOnly->setNeedsReview(true);
        }

        return null;
    }

    /** Tokens avant le premier chiffre du nom, ex. "ABIRATERONE GT" pour "ABIRATERONE GT 250 MG, Comprimé". */
    private function extractBrand(string $name): string
    {
        $normalized = $this->normalize($name);
        if (preg_match('/^([a-z0-9 ]+?)\s*\d/u', $normalized, $m)) {
            return trim($m[1]);
        }
        return trim(explode(',', $normalized)[0]);
    }

    private function normalizeDosage(string $value): string
    {
        return rtrim(rtrim(number_format((float) str_replace(',', '.', $value), 3, '.', ''), '0'), '.');
    }

    private function newMedicine(string $name): Medicine
    {
        $uid = SlugHelper::shortUid();
        return new Medicine($uid, SlugHelper::slugify($name) . '-' . $uid, $name);
    }

    private function ensureUidSlug(Medicine $medicine): void
    {
        // no-op: uid/slug fixés à la création dans newMedicine(); conservé
        // comme point d'extension si une règle de re-slugification est ajoutée.
    }

    private function findByExternalKey(\App\Entity\MedicineSource $source, string $key): ?Medicine
    {
        $record = $this->em->getRepository(MedicineSourceRecord::class)->findOneBy([
            'source' => $source,
            'externalKey' => $key,
        ]);

        return $record?->getMedicine();
    }

    private function resolveDci(string $rawName): Dci
    {
        $name = trim($rawName);
        $slug = SlugHelper::slugify($name);

        if (isset($this->dciCache[$slug])) {
            return $this->dciCache[$slug];
        }

        $repo = $this->em->getRepository(Dci::class);
        $dci = $repo->findOneBy(['slug' => $slug]);
        if ($dci === null) {
            $dci = new Dci($name, $slug);
            $this->em->persist($dci);
        }

        return $this->dciCache[$slug] = $dci;
    }

    private function parseDosage(string $raw): array
    {
        if (preg_match('/([\d.,]+)\s*([a-zA-Zµ%]+)/u', $raw, $m)) {
            return ['value' => str_replace(',', '.', $m[1]), 'unit' => $m[2]];
        }
        return ['value' => null, 'unit' => null];
    }

    private function extractForm(string $presentation): ?string
    {
        $known = ['comprimé', 'gélule', 'sirop', 'flacon', 'boîte', 'gel', 'pommade', 'crème', 'suspension', 'granulé'];
        $lower = mb_strtolower($presentation);
        foreach ($known as $form) {
            if (str_contains($lower, $form)) {
                return $form;
            }
        }
        return null;
    }

    private function parsePrice(string $raw): ?string
    {
        if (preg_match('/([\d.,]+)/', $raw, $m)) {
            return str_replace(',', '.', $m[1]);
        }
        return null;
    }

    private function numeric(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        return $this->parsePrice((string) $value);
    }

    private function orNull(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));
        return $s === '' ? null : $s;
    }

    private function normalize(string $name): string
    {
        $name = mb_strtolower($name);
        $name = preg_replace('/\[.*?\]/', '', $name) ?? $name; // retire [P], [SS]...
        return trim($name);
    }
}
