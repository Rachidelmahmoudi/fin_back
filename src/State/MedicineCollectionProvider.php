<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\MedicineRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class MedicineCollectionProvider implements ProviderInterface
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly MedicineRepository $repository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $request = $this->requestStack->getCurrentRequest();
        $q = $request?->query->get('q');
        $dciOnly = $request?->query->get('dci');
        $reimbursedOnly = $request?->query->getBoolean('cnssReimbursed', false) ?? false;
        $priceMin = $request?->query->get('priceMin');
        $priceMax = $request?->query->get('priceMax');
        $page = (int) ($request?->query->get('page', 1) ?? 1);

        $qb = $this->repository->createQueryBuilder('m')
            ->leftJoin('m.dci', 'd')->addSelect('d')
            // Correctif ajouté en même temps que le filtre prix : sans lui,
            // les médicaments en attente de rapprochement entre sources
            // d'import (CNSS/medicament.ma, needsReview=true — données pas
            // encore fiabilisées) apparaissaient dans la recherche publique.
            ->andWhere('m.needsReview = false');

        if ($q !== null && $q !== '') {
            $qb->andWhere('LOWER(m.name) LIKE :q')->setParameter('q', '%'.mb_strtolower($q).'%');
        }
        if ($dciOnly !== null && $dciOnly !== '') {
            $qb->andWhere('d.slug = :dciSlug')->setParameter('dciSlug', $dciOnly);
        }
        if ($reimbursedOnly) {
            $qb->andWhere('m.ppvCnss IS NOT NULL');
        }

        // Filtre sur le MÊME prix que celui affiché à l'utilisateur
        // (priorité ppvCnss, sinon priceCommercial — voir MedicineResourceMapper)
        // — COALESCE est une fonction DQL standard, contrairement à
        // ACOS/SIN (voir le bug corrigé sur PharmacySearchService), donc
        // pas besoin de SQL natif ici.
        if ($priceMin !== null && $priceMin !== '') {
            $qb->andWhere('COALESCE(m.ppvCnss, m.priceCommercial) >= :priceMin')
                ->setParameter('priceMin', (float) $priceMin);
        }
        if ($priceMax !== null && $priceMax !== '') {
            $qb->andWhere('COALESCE(m.ppvCnss, m.priceCommercial) <= :priceMax')
                ->setParameter('priceMax', (float) $priceMax);
        }

        $qb->orderBy('m.name', 'ASC')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE);

        return array_map(MedicineResourceMapper::map(...), $qb->getQuery()->getResult());
    }
}