<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Medicine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MedicineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Medicine::class);
    }

    public function findOneByEan13(string $ean13): ?Medicine
    {
        return $this->findOneBy(['ean13Code' => $ean13]);
    }

    public function findCandidatesByNameAndDosage(string $normalizedName, ?string $dosageValue): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('LOWER(m.name) LIKE :name')
            ->setParameter('name', '%' . mb_strtolower($normalizedName) . '%');

        if ($dosageValue !== null) {
            $qb->andWhere('m.dosageValue = :dosage')->setParameter('dosage', $dosageValue);
        }

        return $qb->setMaxResults(10)->getQuery()->getResult();
    }

    public function autocompleteNames(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('m')
            ->select('m.id', 'm.name', 'm.dosageValue', 'm.dosageUnit', 'm.presentation')
            ->where('LOWER(m.name) LIKE :q')
            ->setParameter('q', mb_strtolower($query) . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }
}
