<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WalletRechargeRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WalletRechargeRequest>
 */
class WalletRechargeRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WalletRechargeRequest::class);
    }
}
