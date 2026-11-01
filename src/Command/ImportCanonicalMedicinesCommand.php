<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Dci;
use App\Entity\Medicine;
use App\Entity\MedicineSource;
use App\Entity\MedicineSourceRecord;
use App\Entity\MedicineTableau;
use App\Entity\PrincepsOrGeneric;
use App\Service\Import\SlugHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe medicines_canonical.json, produit par tools/medicines/build_canonical.py
 * (normalisation + réconciliation des 3 sources : guide Excel, guide PDF,
 * medicament.ma). Une ligne du fichier = UN médicament.
 *
 * IDEMPOTENT : relancer l'import met à jour au lieu de dupliquer.
 * Identité d'un médicament, dans l'ordre :
 *   1. son code EAN13 ;
 *   2. sinon une ligne medicine_source_record déjà connue (source + clé externe,
 *      ex. l'URL medicament.ma) ;
 *   3. sinon : création.
 *
 * Les prix du guide sont déjà en dirhams dans le fichier canonique (le
 * fichier Excel d'origine les donne en centimes).
 */
#[AsCommand(name: 'app:import:medicines:canonical', description: 'Importe le fichier canonique de médicaments (3 sources fusionnées)')]
final class ImportCanonicalMedicinesCommand extends Command
{
    private const SOURCES = [
        'cnss_guide_xlsx' => MedicineSource::CNSS_GUIDE_XLSX,
        'cnss_guide_pdf' => MedicineSource::CNSS_GUIDE_PDF,
        'medicament_ma' => MedicineSource::MEDICAMENT_MA,
    ];

    /** Longueurs des colonnes VARCHAR de la table medicine. */
    private const MAX = [
        'name' => 255, 'brandName' => 180, 'dosageUnit' => 20, 'form' => 255, 'presentation' => 255,
        'manufacturer' => 180, 'therapeuticClass' => 255, 'atcCode' => 20, 'status' => 60, 'matchKey' => 255, 'dciName' => 255,
    ];

    /** @var array<string, true> */
    private array $usedSlugs = [];

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin vers medicines_canonical.json (ou .json.gz)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Analyse sans rien écrire en base')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Taille des lots (flush)', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string) $input->getArgument('file');
        $dry = (bool) $input->getOption('dry-run');
        $batch = max(1, (int) $input->getOption('batch'));

        if (!is_file($path)) {
            $io->error("Fichier introuvable : {$path}");

            return Command::FAILURE;
        }

        $content = file_get_contents($path);
        if (str_ends_with($path, '.gz')) {
            $content = gzdecode($content);
        }
        $records = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $conn = $this->em->getConnection();
        foreach ($conn->fetchFirstColumn('SELECT slug FROM medicine') as $slug) {
            $this->usedSlugs[$slug] = true;
        }

        $stats = ['created' => 0, 'updated' => 0, 'errors' => 0];
        $dciCache = [];
        $io->progressStart(\count($records));
        $n = 0;

        foreach ($records as $record) {
            $io->progressAdvance();
            ++$n;
            $name = $this->cut((string) ($record['name'] ?? ''), 'name');
            if ($name === null || $name === '') {
                ++$stats['errors'];
                continue;
            }

            $medicine = $this->findExisting($record);
            if ($medicine === null) {
                $medicine = new Medicine(SlugHelper::shortUid(), $this->uniqueSlug(SlugHelper::slugify($name)), $name);
                $this->em->persist($medicine);
                ++$stats['created'];
            } else {
                $medicine->setName($name);
                ++$stats['updated'];
            }

            $this->apply($medicine, $record, $dciCache);
            $this->syncSources($medicine, $record);

            if (!$dry && $n % $batch === 0) {
                $this->flushAndReset($dciCache);
            }
        }

        if (!$dry) {
            $this->flushAndReset($dciCache);
        }
        $io->progressFinish();

        $io->success(sprintf(
            '%s%d créés, %d mis à jour, %d ignorés sur %d entrées.',
            $dry ? '[DRY-RUN — rien n\'est enregistré] ' : '',
            $stats['created'],
            $stats['updated'],
            $stats['errors'],
            \count($records),
        ));

        return Command::SUCCESS;
    }

    /** @param array<string, Dci> $dciCache */
    private function flushAndReset(array &$dciCache): void
    {
        $this->em->flush();
        $this->em->clear();
        $dciCache = [];
    }

    private function findExisting(array $record): ?Medicine
    {
        $ean = !empty($record['ean13Code']) ? (string) $record['ean13Code'] : null;
        if ($ean !== null) {
            $found = $this->em->getRepository(Medicine::class)->findOneBy(['ean13Code' => $ean]);
            if ($found !== null) {
                return $found;
            }
        }
        foreach ($record['sources'] ?? [] as $src) {
            $enum = self::SOURCES[$src['source']] ?? null;
            if ($enum === null) {
                continue;
            }
            $sr = $this->em->getRepository(MedicineSourceRecord::class)->findOneBy(['source' => $enum, 'externalKey' => $this->cut($src['externalKey'], 'externalKey')]);
            if ($sr !== null) {
                return $sr->getMedicine();
            }
        }

        return null;
    }

    /** @param array<string, Dci> $dciCache */
    private function apply(Medicine $m, array $r, array &$dciCache): void
    {
        if (!empty($r['dciName'])) {
            $dciName = trim((string) $r['dciName']);
            $slug = SlugHelper::slugify($dciName);
            if ($slug !== '') {
                if (!isset($dciCache[$slug])) {
                    $dci = $this->em->getRepository(Dci::class)->findOneBy(['slug' => $slug]) ?? new Dci($this->cut($dciName, 'dciName'), $slug);
                    $this->em->persist($dci);
                    $dciCache[$slug] = $dci;
                }
                $m->setDci($dciCache[$slug]);
            }
        }

        $m->setBrandName($this->cut($r['brandName'] ?? null, 'brandName'));
        $m->setDosageValue($this->dec($r['dosageValue'] ?? null));
        $m->setDosageUnit($this->cut($r['dosageUnit'] ?? null, 'dosageUnit'));
        $m->setForm($this->cut($r['form'] ?? null, 'form'));
        $m->setPresentation($this->cut($r['presentation'] ?? null, 'presentation'));
        $m->setPackQuantity(isset($r['packQuantity']) ? (int) $r['packQuantity'] : null);
        $m->setManufacturer($this->cut($r['manufacturer'] ?? null, 'manufacturer'));
        $m->setTherapeuticClass($this->cut($r['therapeuticClass'] ?? null, 'therapeuticClass'));
        $m->setAtcCode($this->cut($r['atcCode'] ?? null, 'atcCode'));
        $m->setStatus($this->cut($r['status'] ?? null, 'status'));
        $m->setMatchKey($this->cut($r['matchKey'] ?? null, 'matchKey'));
        $m->setTableau(\in_array($r['tableau'] ?? null, ['A', 'B'], true) ? MedicineTableau::from($r['tableau']) : null);
        $m->setPrincepsOrGeneric(\in_array($r['princepsOrGeneric'] ?? null, ['P', 'G'], true) ? PrincepsOrGeneric::from($r['princepsOrGeneric']) : null);
        $m->setIndications($this->text($r['indications'] ?? null));
        $m->setContraindications($this->text($r['contraindications'] ?? null));
        $m->setPrecautions($this->text($r['precautions'] ?? null));
        $m->setEan13Code(!empty($r['ean13Code']) ? (string) $r['ean13Code'] : null);
        $m->setPriceCommercial($this->dec($r['priceCommercial'] ?? null));
        $m->setPriceHospitalCommercial($this->dec($r['priceHospitalCommercial'] ?? null));
        $m->setPpvCnss($this->dec($r['ppvCnss'] ?? null));
        $m->setPhCnss($this->dec($r['phCnss'] ?? null));
        $m->setPpvBrCnss($this->dec($r['ppvBrCnss'] ?? null));
        $m->setPhBrCnss($this->dec($r['phBrCnss'] ?? null));
        $m->setIsCnssReimbursed((bool) ($r['isCnssReimbursed'] ?? false));
        $m->setReimbursementRate($this->dec($r['reimbursementRate'] ?? null));
        $m->setExonerationRate($this->dec($r['exonerationRate'] ?? null));
        $m->setExonerationEligible(isset($r['exonerationEligible']) ? (bool) $r['exonerationEligible'] : null);
        $m->setNeedsReview((bool) ($r['needsReview'] ?? false));
        $m->setReviewReasons(!empty($r['reviewReasons']) ? array_values($r['reviewReasons']) : null);
    }

    private function syncSources(Medicine $m, array $r): void
    {
        foreach ($r['sources'] ?? [] as $src) {
            $enum = self::SOURCES[$src['source']] ?? null;
            if ($enum === null) {
                continue;
            }
            $key = (string) $this->cut($src['externalKey'], 'externalKey');
            $sr = $this->em->getRepository(MedicineSourceRecord::class)->findOneBy(['source' => $enum, 'externalKey' => $key]);
            if ($sr === null) {
                $this->em->persist(new MedicineSourceRecord($m, $enum, $key, $r));
            } else {
                $sr->refresh($m, $r);
            }
        }
    }

    private function cut(mixed $v, string $field): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        $max = self::MAX[$field] ?? 255;

        return mb_strlen($v) > $max ? mb_substr($v, 0, $max - 1).'…' : $v;
    }

    private function text(mixed $v): ?string
    {
        $v = $v === null ? null : trim((string) $v);

        return $v === null || $v === '' ? null : mb_substr($v, 0, 20000);
    }

    private function dec(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_numeric($v) ? (string) $v : null;
    }

    private function uniqueSlug(string $base): string
    {
        $base = $base !== '' ? $base : 'medicament';
        $slug = $base;
        $i = 2;
        while (isset($this->usedSlugs[$slug])) {
            $slug = $base.'-'.$i++;
        }
        $this->usedSlugs[$slug] = true;

        return $slug;
    }
}
