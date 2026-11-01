<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Dci;
use App\Entity\Medicine;
use App\Entity\MedicineSourceRecord;
use App\Entity\MedicineTableau;
use App\Entity\PrincepsOrGeneric;
use App\Repository\MedicineRepository;
use App\Service\Import\SlugHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe le fichier de fusion (scraping medicament.ma A-Z + guide CNSS)
 * produit hors-projet — voir RAPPORT_FUSION.md pour le détail de la
 * logique de fusion (clé marque+dosage, priorités de champs par source).
 *
 * Remplace à terme les deux commandes séparées
 * (ImportMedicinesFromMedicamentMaCommand, ImportMedicinesFromCnssGuideCommand,
 * Phase 4a) — celles-ci restent en place mais ne devraient plus être
 * relancées après ce nouvel import, sous peine de créer des doublons
 * (aucune déduplication entre elles et ce nouvel import n'a été prévue).
 *
 * Idempotent PAR EAN13 quand présent (une deuxième exécution met à jour
 * plutôt que dupliquer) — mais PAS idempotent pour les entrées sans
 * EAN13 (uniquement scraping, ~3547 entrées) : celles-ci seraient
 * dupliquées à chaque nouvelle exécution. Ne lancer qu'une fois sur une
 * base vierge, ou vider la table `medicine` avant relance.
 */
#[AsCommand(name: 'app:import:medicines:merged', description: 'Importe le fichier JSON fusionné (scraping + guide CNSS)')]
final class ImportMergedMedicinesCommand extends Command
{
    /**
     * Mémorise tous les slugs déjà attribués pendant CET import (+ ceux
     * déjà en base au démarrage) pour détecter les collisions — plusieurs
     * produits différents partagent souvent le même nom de marque (ex :
     * "ACCUPRIL" en plusieurs dosages), donc le même slug de base, alors
     * que la colonne `slug` est unique en base (contrainte
     * uniq_medicine_slug). Propriété de CETTE classe Command, pas une
     * entité — elle vit le temps de l'exécution de la commande, comme
     * n'importe quelle variable d'instance PHP.
     *
     * @var array<string, true>
     */
    private array $usedSlugs = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MedicineRepository $medicineRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Chemin vers medicines_merged.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = $input->getArgument('file');

        if (!is_file($path)) {
            $io->error("Fichier introuvable : {$path}");

            return Command::FAILURE;
        }

        // Pré-charge les slugs déjà en base (import précédent partiel, ou
        // médicaments déjà importés par les anciennes commandes séparées)
        // pour ne pas entrer en collision avec eux non plus.
        foreach ($this->em->getConnection()->fetchFirstColumn('SELECT slug FROM medicine') as $existingSlug) {
            $this->usedSlugs[$existingSlug] = true;
        }

        $records = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $io->progressStart(\count($records));

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $dciCache = [];
        $batchSize = 200;
        $i = 0;

        foreach ($records as $record) {
            $io->progressAdvance();
            ++$i;

            $name = trim((string) ($record['name'] ?? ''));
            if ($name === '') {
                ++$skipped;
                continue;
            }

            $ean13 = $record['ean13Code'] !== null ? (string) $record['ean13Code'] : null;

            // Idempotence par EAN13 quand disponible (voir docblock de la classe).
            $medicine = $ean13 !== null ? $this->medicineRepository->findOneBy(['ean13Code' => $ean13]) : null;

            if ($medicine === null) {
                $medicine = new Medicine(SlugHelper::shortUid(), $this->uniqueSlug(SlugHelper::slugify($name)), $this->truncate($name));
                $this->em->persist($medicine);
                ++$created;
            } else {
                ++$updated;
            }

            $this->applyRecord($medicine, $record, $dciCache);

            foreach ($record['_sources'] as $source) {
                $sourceEnum = $source === 'cnss_guide' ? \App\Entity\MedicineSource::CNSS_GUIDE : \App\Entity\MedicineSource::MEDICAMENT_MA;
                $externalKey = $source === 'cnss_guide' ? ($ean13 ?? $name) : ($record['sourceUrl'] ?? $name);
                $sourceRecord = new MedicineSourceRecord($medicine, $sourceEnum, $this->truncate((string) $externalKey), $record);
                $this->em->persist($sourceRecord);
            }

            // §DIAGNOSTIC TEMPORAIRE : flush() à CHAQUE médicament (pas par
            // lot de 200) pour identifier précisément lequel plante — un
            // flush() par lot ne dit pas lequel des ~200 en attente est en
            // cause. À remettre en lot de 200 une fois le vrai champ fautif
            // identifié (bien plus lent tel quel sur 11k+ entrées).
            try {
                $this->em->flush();
            } catch (\Throwable $e) {
                $io->error('Échec sur le médicament : '.$name);
                foreach ($record as $field => $value) {
                    if (\is_string($value)) {
                        $io->writeln(sprintf('  %-25s %4d car. : %s', $field, mb_strlen($value), mb_substr($value, 0, 80)));
                    } elseif ($value !== null && !\is_array($value)) {
                        $io->writeln(sprintf('  %-25s (%s) : %s', $field, get_debug_type($value), $value));
                    }
                }

                // §DIAGNOSTIC : le message Symfony/DBAL de haut niveau ne
                // nomme pas la colonne. L'exception PDO d'origine (via
                // getPrevious(), potentiellement plusieurs niveaux) contient
                // le vrai message serveur PostgreSQL, qui lui nomme la
                // colonne exacte.
                $io->section('Détail complet de l\'exception');
                $current = $e;
                $depth = 0;
                while ($current !== null) {
                    $io->writeln(sprintf('[niveau %d] %s: %s', $depth, $current::class, $current->getMessage()));
                    $current = $current->getPrevious();
                    ++$depth;
                }

                throw $e;
            }
            $this->em->clear();
            $dciCache = [];
        }

        $io->progressFinish();

        $io->success(sprintf(
            '%d créés, %d mis à jour, %d ignorés (nom vide) sur %d entrées.',
            $created,
            $updated,
            $skipped,
            \count($records),
        ));

        return Command::SUCCESS;
    }

    /**
     * Tronque toute valeur destinée à une colonne VARCHAR(255) — le guide
     * CNSS/scraping contient quelques valeurs aberrantes qui dépassent
     * cette limite (ex: une DCI multi-ingrédients listant 9+ composants
     * séparés par "|", ou une classe thérapeutique où le site source a
     * visiblement collé le texte des indications par erreur). Tronquer
     * plutôt que planter : l'info est déjà partiellement dégradée à la
     * source dans ces cas rares (moins de 1% des lignes), mieux vaut un
     * enregistrement importé avec un champ raccourci qu'un import qui
     * s'arrête net dessus.
     */
    private function truncate(?string $value, int $maxLength = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength - 1).'…' : $value;
    }

    private function uniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $suffix = 2;
        while (isset($this->usedSlugs[$slug])) {
            $slug = $baseSlug.'-'.$suffix;
            ++$suffix;
        }
        $this->usedSlugs[$slug] = true;

        return $slug;
    }

    /** @param array<string, Dci> $dciCache */
    private function applyRecord(Medicine $medicine, array $record, array &$dciCache): void
    {
        if (!empty($record['dciName'])) {
            $dciName = trim((string) $record['dciName']);
            // §Correctif : recherche par SLUG (normalisé, insensible à la
            // casse/accents), pas par nom brut — la même DCI apparaît
            // orthographiée différemment selon la source ("Alendronate" côté
            // scraping, "ALENDRONATE" côté CNSS) ; une recherche par nom exact
            // ne les reconnaît pas comme la même DCI et en recrée une
            // deuxième, qui se normalise vers le MÊME slug que la première
            // -> collision sur la contrainte d'unicité. Le slug est le seul
            // identifiant fiable ici puisque c'est lui qui doit rester unique.
            $dciSlug = SlugHelper::slugify($dciName);
            if (!isset($dciCache[$dciSlug])) {
                $dci = $this->em->getRepository(Dci::class)->findOneBy(['slug' => $dciSlug])
                    ?? new Dci($this->truncate($dciName), $dciSlug);
                $this->em->persist($dci);
                $dciCache[$dciSlug] = $dci;
            }
            $medicine->setDci($dciCache[$dciSlug]);
        }

        $presentationSuffix = '';
        if (isset($record['dosageValue']) && $record['dosageValue'] !== null && $record['dosageValue'] !== '') {
            $rawDosage = trim((string) $record['dosageValue']);
            // §Correctif : certains médicaments combinés (2 principes actifs)
            // ont un dosage exprimé en ratio par le guide CNSS (ex: "1 / 125",
            // "500 / 62.5") — pas une valeur numérique valide pour la colonne
            // dosageValue (decimal en base). On extrait le PREMIER nombre pour
            // la colonne numérique, et on garde le ratio complet dans la
            // présentation plutôt que de perdre l'info (8,6% des entrées du
            // fichier fusionné sont dans ce cas — assez fréquent pour ne pas
            // juste l'ignorer silencieusement).
            if (preg_match('/-?\d+(\.\d+)?/', $rawDosage, $matches)) {
                $medicine->setDosageValue($matches[0]);
                if ($rawDosage !== $matches[0]) {
                    $presentationSuffix = ' (dosage combiné : '.$rawDosage.')';
                }
            }
        }
        if (!empty($record['dosageUnit'])) {
            $medicine->setDosageUnit($this->truncate((string) $record['dosageUnit']));
        }
        if (!empty($record['form'])) {
            $medicine->setForm($this->truncate((string) $record['form']));
        }
        if (!empty($record['presentation']) || $presentationSuffix !== '') {
            $medicine->setPresentation($this->truncate((string) ($record['presentation'] ?? '').$presentationSuffix));
        }
        if (!empty($record['manufacturer'])) {
            $medicine->setManufacturer($this->truncate((string) $record['manufacturer']));
        }
        if (!empty($record['therapeuticClass'])) {
            $medicine->setTherapeuticClass($this->truncate((string) $record['therapeuticClass']));
        }
        if (!empty($record['atcCode'])) {
            $medicine->setAtcCode($this->truncate((string) $record['atcCode']));
        }
        if (!empty($record['tableau']) && \in_array($record['tableau'], ['A', 'B'], true)) {
            $medicine->setTableau(MedicineTableau::from($record['tableau']));
        }
        if (!empty($record['princepsOrGeneric']) && \in_array($record['princepsOrGeneric'], ['P', 'G'], true)) {
            $medicine->setPrincepsOrGeneric(PrincepsOrGeneric::from($record['princepsOrGeneric']));
        }
        if (isset($record['priceCommercial'])) {
            $medicine->setPriceCommercial((string) $record['priceCommercial']);
        }
        if (isset($record['priceHospitalCommercial'])) {
            $medicine->setPriceHospitalCommercial((string) $record['priceHospitalCommercial']);
        }
        if (!empty($record['indications'])) {
            $medicine->setIndications($this->truncate((string) $record['indications'], 5000));
        }
        if (!empty($record['contraindications'])) {
            $medicine->setContraindications($this->truncate((string) $record['contraindications'], 5000));
        }
        if (!empty($record['precautions'])) {
            $medicine->setPrecautions($this->truncate((string) $record['precautions'], 5000));
        }
        if (!empty($record['ean13Code'])) {
            $medicine->setEan13Code((string) $record['ean13Code']);
        }
        if (isset($record['ppvCnss'])) {
            $medicine->setPpvCnss((string) $record['ppvCnss']);
        }
        if (isset($record['phCnss'])) {
            $medicine->setPhCnss((string) $record['phCnss']);
        }
        if (isset($record['ppvBrCnss'])) {
            $medicine->setPpvBrCnss((string) $record['ppvBrCnss']);
        }
        if (isset($record['reimbursementRate'])) {
            $medicine->setReimbursementRate((string) $record['reimbursementRate']);
        }
        if (isset($record['exonerationRate'])) {
            $medicine->setExonerationRate((string) $record['exonerationRate']);
        }
        if (isset($record['exonerationEligible'])) {
            $medicine->setExonerationEligible((bool) $record['exonerationEligible']);
        }
        $medicine->setNeedsReview((bool) ($record['_needsReview'] ?? true));
    }
}