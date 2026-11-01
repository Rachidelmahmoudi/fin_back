<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PrescriptionResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrescriptionResult>
 */
class PrescriptionResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrescriptionResult::class);
    }
}
