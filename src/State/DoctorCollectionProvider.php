<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\EstablishmentStatus;
use App\Repository\DoctorRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class DoctorCollectionProvider implements ProviderInterface
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly DoctorRepository $repository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = (int) ($request?->query->get('page', 1) ?? 1);

        $qb = $this->repository->createQueryBuilder('d')
            ->innerJoin('d.city', 'c')
            ->andWhere('d.status = :status')
            ->setParameter('status', EstablishmentStatus::PUBLISHED);

        if (($city = $request?->query->get('city')) !== null) {
            $qb->andWhere('c.slug = :citySlug')->setParameter('citySlug', $city);
        }

        if (($specialty = $request?->query->get('specialty')) !== null) {
            $qb->innerJoin('d.specialties', 's')->andWhere('s.slug = :specialtySlug')->setParameter('specialtySlug', $specialty);
        }

        $qb->orderBy('d.name', 'ASC')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE);

        return array_map(DoctorResourceMapper::map(...), $qb->getQuery()->getResult());
    }
}
