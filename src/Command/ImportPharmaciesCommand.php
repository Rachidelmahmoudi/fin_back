<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Import\PharmacyImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:pharmacies',
    description: 'Importe/synchronise les pharmacies depuis un fichier JSON (format pharmacies.json)',
)]
final class ImportPharmaciesCommand extends Command
{
    private const BATCH_SIZE = 200;

    public function __construct(
        private readonly PharmacyImportService $importService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Chemin vers le fichier JSON à importer');
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
        if (!is_array($rows)) {
            $io->error('JSON invalide: attendu un tableau de pharmacies.');
            return Command::FAILURE;
        }

        $io->title(sprintf('Import de %d pharmacies depuis %s', count($rows), $file));
        $progress = $io->createProgressBar(count($rows));

        $counts = ['created' => 0, 'updated' => 0, 'merged_duplicate' => 0, 'skipped' => 0];

        foreach ($rows as $i => $row) {
            try {
                $result = $this->importService->importRow($row);
                $counts[$result['action']]++;
            } catch (\InvalidArgumentException $e) {
                $counts['skipped']++;
            }

            if (($i + 1) % self::BATCH_SIZE === 0) {
                $this->em->flush();
                $this->em->clear(); // libère la mémoire sur un import de 18k+ lignes
            }

            $progress->advance();
        }

        $this->em->flush();
        $progress->finish();
        $io->newLine(2);

        $io->table(
            ['Créées', 'Mises à jour', 'Doublons fusionnés', 'Ignorées'],
            [[$counts['created'], $counts['updated'], $counts['merged_duplicate'], $counts['skipped']]],
        );

        return Command::SUCCESS;
    }
}
