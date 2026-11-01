<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Import\MedicineImportService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:medicines:cnss-guide',
    description: 'Importe le Guide des Médicaments Remboursables CNSS (Medication_liste1.xlsx). ' .
        'Nécessite phpoffice/phpspreadsheet (composer require phpoffice/phpspreadsheet).',
)]
final class ImportMedicinesFromCnssGuideCommand extends Command
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly MedicineImportService $importService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Chemin vers Medication_liste1.xlsx');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');

        if (!is_file($file)) {
            $io->error("Fichier introuvable: {$file}");
            return Command::FAILURE;
        }

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rowsIterator = $sheet->getRowIterator(2); // ligne 1 = en-têtes, voir Phase 1

        $headers = [
            'Code', 'Laboratoire', 'Nom', 'DCI1', 'Dosage1', 'Unite Dosage1', 'Forme',
            'Présentation', 'PPV', 'PH', 'PPV - BR', 'Princeps Generique',
            'Classe Thérapeutique', 'TAUX Remboursement', 'Taux Exoneration',
            "Droit à l'exonération",
        ];

        $io->title(sprintf('Import du guide CNSS depuis %s', $file));
        $count = 0;

        foreach ($rowsIterator as $row) {
            $cells = [];
            $colIndex = 0;
            foreach ($row->getCellIterator() as $cell) {
                if (!isset($headers[$colIndex])) {
                    break;
                }
                $cells[$headers[$colIndex]] = $cell->getValue();
                $colIndex++;
            }

            if (($cells['Code'] ?? null) === null) {
                continue; // ligne vide
            }

            $this->importService->importFromCnssGuide($cells);
            $count++;

            if ($count % self::BATCH_SIZE === 0) {
                $this->em->flush();
                $this->em->clear();
                $io->write('.');
            }
        }

        $this->em->flush();
        $io->newLine();
        $io->success(sprintf('%d lignes CNSS importées. Lancer ensuite app:medicines:reconcile.', $count));

        return Command::SUCCESS;
    }
}
