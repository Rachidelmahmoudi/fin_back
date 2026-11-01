<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DoctorOpeningHour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DoctorOpeningHour>
 */
class DoctorOpeningHourRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DoctorOpeningHour::class);
    }
}
