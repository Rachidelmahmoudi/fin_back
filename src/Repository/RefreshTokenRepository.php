<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findValidByHash(string $tokenHash): ?RefreshToken
    {
        return $this->createQueryBuilder('rt')
            ->andWhere('rt.tokenHash = :hash')
            ->andWhere('rt.revokedAt IS NULL')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('hash', $tokenHash)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Révocation globale (déconnexion "partout") — §18, endpoint logout. */
    public function revokeAllForUser(\App\Entity\User $user): void
    {
        $this->createQueryBuilder('rt')
            ->update()
            ->set('rt.revokedAt', ':now')
            ->andWhere('rt.user = :user')
            ->andWhere('rt.revokedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
