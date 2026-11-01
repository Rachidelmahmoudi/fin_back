<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Availability\PharmacyAvailabilityService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * À planifier toutes les 15 minutes (cron système ou Symfony Scheduler) :
 * *\/15 * * * * bin/console app:pharmacy:recalculate-availability
 */
#[AsCommand(name: 'app:pharmacy:recalculate-availability', description: 'Recalcule les flags isOpenNow/isOnDutyNow de toutes les pharmacies actives')]
final class RecalculatePharmacyAvailabilityCommand extends Command
{
    public function __construct(private readonly PharmacyAvailabilityService $service)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $changed = $this->service->recalculateAll();
        $io->success(sprintf('%d pharmacie(s) mise(s) à jour.', $changed));

        return Command::SUCCESS;
    }
}
