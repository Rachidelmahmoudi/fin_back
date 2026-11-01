<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Pharmacy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PharmacyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pharmacy::class);
    }

    public function findOneByLegacyReference(string $reference): ?Pharmacy
    {
        return $this->findOneBy(['legacyReference' => $reference]);
    }
}
