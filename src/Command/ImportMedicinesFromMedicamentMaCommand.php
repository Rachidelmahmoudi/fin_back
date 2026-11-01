<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Import\MedicineImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:medicines:medicament-ma',
    description: 'Importe les médicaments depuis un fichier medications_X.json (scraping medicament.ma). ' .
        'Prévu pour être appelé une fois par lettre (medications_A.json, medications_B.json, ...).',
)]
final class ImportMedicinesFromMedicamentMaCommand extends Command
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
        $this->addArgument('file', InputArgument::REQUIRED, 'Chemin vers medications_X.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');

        if (!is_file($file)) {
            $io->error("Fichier introuvable: {$file}");
            return Command::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        $io->title(sprintf('Import de %d médicaments (medicament.ma) depuis %s', count($rows), $file));
        $progress = $io->createProgressBar(count($rows));

        foreach ($rows as $i => $row) {
            $this->importService->importFromMedicamentMa($row);

            if (($i + 1) % self::BATCH_SIZE === 0) {
                $this->em->flush();
                $this->em->clear();
            }
            $progress->advance();
        }

        $this->em->flush();
        $progress->finish();
        $io->newLine(2);
        $io->success('Import terminé. Lancer ensuite app:import:medicines:cnss-guide puis app:medicines:reconcile.');

        return Command::SUCCESS;
    }
}
