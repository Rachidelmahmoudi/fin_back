<?php

declare(strict_types=1);

namespace App\Service\Availability;

use App\Entity\Pharmacy;
use App\Repository\PharmacyRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * §Phase 3 : "statut garde/ouvert calculé, pas seulement stocké". Ce
 * service recalcule les flags dénormalisés Pharmacy::isOpenNow/isOnDutyNow
 * à partir de PharmacyOpeningHour / PharmacyDutyPeriod.
 *
 * À brancher sur un Symfony Scheduler / cron toutes les 15 minutes
 * (`bin/console app:pharmacy:recalculate-availability`), plutôt que
 * recalculé à chaque requête de listing — sur 18k+ pharmacies, recalculer à
 * la volée à chaque GET /pharmacies serait inutilement coûteux alors que la
 * précision à la minute près n'est pas nécessaire pour ce cas d'usage.
 */
final class PharmacyAvailabilityService
{
    public function __construct(
        private readonly PharmacyRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** Recalcule et persiste les flags pour toutes les pharmacies actives. Retourne le nombre de lignes modifiées. */
    public function recalculateAll(): int
    {
        $now = new \DateTimeImmutable();
        $changed = 0;

        // batch_size volontairement modeste : 18k+ lignes, on veut éviter de
        // charger toute la table en mémoire d'un coup (traitement par lots
        // avec clear() périodique, pattern standard Doctrine batch UPDATE).
        $batchSize = 200;
        $offset = 0;

        do {
            $batch = $this->repository->createQueryBuilder('p')
                ->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();

            foreach ($batch as $pharmacy) {
                /** @var Pharmacy $pharmacy */
                $newOpen = $this->computeIsOpenNow($pharmacy, $now);
                $newDuty = $this->computeIsOnDutyNow($pharmacy, $now);

                if ($newOpen !== $pharmacy->isOpenNow() || $newDuty !== $pharmacy->isOnDutyNow()) {
                    $pharmacy->setIsOpenNow($newOpen);
                    $pharmacy->setIsOnDutyNow($newDuty);
                    ++$changed;
                }
            }

            $this->em->flush();
            $this->em->clear();
            $offset += $batchSize;
        } while (\count($batch) === $batchSize);

        return $changed;
    }

    private function computeIsOpenNow(Pharmacy $pharmacy, \DateTimeImmutable $now): bool
    {
        $dayOfWeek = (int) $now->format('N'); // 1 (lundi) à 7 (dimanche)
        $currentTime = $now->format('H:i:s');

        foreach ($pharmacy->getOpeningHours() as $hour) {
            if ($hour->getDayOfWeek() !== $dayOfWeek) {
                continue;
            }
            $opensAt = $hour->getOpensAt()->format('H:i:s');
            $closesAt = $hour->getClosesAt()->format('H:i:s');
            if ($currentTime >= $opensAt && $currentTime <= $closesAt) {
                return true;
            }
        }

        return false;
    }

    private function computeIsOnDutyNow(Pharmacy $pharmacy, \DateTimeImmutable $now): bool
    {
        foreach ($pharmacy->getDutyPeriods() as $period) {
            if ($period->isActiveAt($now)) {
                return true;
            }
        }

        return false;
    }
}
