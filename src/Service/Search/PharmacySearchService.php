<?php

declare(strict_types=1);

namespace App\Service\Search;

use App\Entity\Pharmacy;
use App\Entity\PharmacyStatus;
use App\Repository\PharmacyRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

/**
 * Centralise la logique de recherche pharmacie pour ne pas la disperser
 * entre controllers Web (Twig) et providers API Platform (Phase 3, §3.2) —
 * les deux appellent ce même service.
 *
 * Le filtre `medicineId` (recherche "quelles pharmacies ont ce médicament")
 * s'appuie sur StockItem (§Addendum C) — non encore modélisé en Phase 4a/4b,
 * le paramètre est accepté mais ignoré pour l'instant (TODO Phase 4c/4d,
 * dépend de la fonctionnalité de gestion de stock côté pharmacien).
 *
 * §Correctif (bug remonté en local, deux fois de suite — vérifie bien que
 * CE fichier remplace l'ancien cette fois) : le calcul de distance
 * Haversine (ACOS/COS/SIN/RADIANS) utilisait le QueryBuilder de l'ORM, qui
 * parle DQL — un langage propre à Doctrine, PAS du SQL, et qui ne connaît
 * pas ces fonctions trigonométriques nativement ("Expected known
 * function, got 'ACOS'"). La branche avec géolocalisation est réécrite
 * avec le QueryBuilder de DBAL (accessible via Connection) : celui-là
 * génère du vrai SQL, où ces fonctions sont valides. On récupère les ids +
 * distances via DBAL, puis on hydrate les entités Pharmacy via l'ORM en
 * conservant l'ordre déjà trié par distance. La branche SANS
 * géolocalisation (plus bas) reste inchangée, en DQL classique — elle n'a
 * jamais eu ce problème, aucune fonction trigonométrique n'y intervient.
 */
final class PharmacySearchService
{
    private const PAGE_SIZE = 20;
    private const EARTH_RADIUS_KM = 6371;

    public function __construct(
        private readonly PharmacyRepository $repository,
        private readonly Connection $connection,
    ) {
    }

    public function search(
        ?string $city = null,
        ?string $query = null,
        ?float $lat = null,
        ?float $lng = null,
        ?float $radiusKm = null,
        bool $onDutyOnly = false,
        bool $openNowOnly = false,
        ?string $medicineId = null,
        int $page = 1,
    ): PharmacySearchResult {
        if ($lat !== null && $lng !== null) {
            return $this->searchWithDistance($city, $query, $lat, $lng, $radiusKm, $onDutyOnly, $openNowOnly, $page);
        }

        return $this->searchWithoutDistance($city, $query, $onDutyOnly, $openNowOnly, $page);
    }

    /**
     * Branche géolocalisée — SQL natif via DBAL (voir le commentaire de
     * classe pour le pourquoi). Sous-requête nécessaire : PostgreSQL
     * n'autorise pas de réutiliser un alias de SELECT ("distance_km")
     * directement dans un WHERE/HAVING de la même requête sans GROUP BY.
     */
    private function searchWithDistance(
        ?string $city,
        ?string $query,
        float $lat,
        float $lng,
        ?float $radiusKm,
        bool $onDutyOnly,
        bool $openNowOnly,
        int $page,
    ): PharmacySearchResult {
        $inner = $this->connection->createQueryBuilder();
        $inner->select(
            'p.id AS id',
            sprintf(
                '(%d * ACOS(
                    COS(RADIANS(:lat)) * COS(RADIANS(p.lat::float8)) * COS(RADIANS(p.lng::float8) - RADIANS(:lng))
                    + SIN(RADIANS(:lat)) * SIN(RADIANS(p.lat::float8))
                )) AS distance_km',
                self::EARTH_RADIUS_KM,
            ),
        )
            ->from('pharmacy', 'p')
            ->innerJoin('p', 'city', 'c', 'c.id = p.city_id')
            ->andWhere('p.status = :status')
            ->andWhere('p.lat IS NOT NULL')
            ->andWhere('p.lng IS NOT NULL')
            ->setParameter('lat', $lat)
            ->setParameter('lng', $lng)
            ->setParameter('status', PharmacyStatus::ACTIVE->value);

        if ($city !== null && $city !== '') {
            $inner->andWhere('c.slug = :citySlug')->setParameter('citySlug', $city);
        }
        if ($query !== null && $query !== '') {
            $inner->andWhere('(LOWER(p.name) LIKE :q OR LOWER(p.address_raw) LIKE :q)')
                ->setParameter('q', '%'.mb_strtolower($query).'%');
        }
        if ($onDutyOnly) {
            $inner->andWhere('p.is_on_duty_now = true');
        }
        if ($openNowOnly) {
            $inner->andWhere('p.is_open_now = true');
        }

        $outer = $this->connection->createQueryBuilder();
        $outer->select('sub.id', 'sub.distance_km')
            ->from('('.$inner->getSQL().')', 'sub')
            ->orderBy('sub.distance_km', 'ASC')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE);

        if ($radiusKm !== null) {
            $outer->andWhere('sub.distance_km <= :radiusKm')->setParameter('radiusKm', $radiusKm);
        }

        foreach ($inner->getParameters() as $key => $value) {
            $outer->setParameter($key, $value, $inner->getParameterType($key) ?? ParameterType::STRING);
        }
        if ($radiusKm !== null) {
            $outer->setParameter('radiusKm', $radiusKm);
        }

        $rows = $outer->executeQuery()->fetchAllAssociative();

        if ($rows === []) {
            return new PharmacySearchResult([], [], 0);
        }

        $ids = array_map(static fn (array $row) => $row['id'], $rows);
        $distances = [];
        foreach ($rows as $row) {
            $distances[$row['id']] = round((float) $row['distance_km'], 2);
        }

        $entities = $this->repository->findBy(['id' => array_map(static fn (string $id) => Uuid::fromString($id), $ids)]);
        $byId = [];
        foreach ($entities as $entity) {
            $byId[$entity->getId()->toRfc4122()] = $entity;
        }
        $items = array_values(array_filter(array_map(static fn (string $id) => $byId[$id] ?? null, $ids)));

        $distancesByEntityId = [];
        /** @var Pharmacy $item */
        foreach ($items as $item) {
            $distancesByEntityId[$item->getId()->toRfc4122()] = $distances[$item->getId()->toRfc4122()] ?? null;
        }

        return new PharmacySearchResult($items, $distancesByEntityId, \count($items));
    }

    /** Branche sans géolocalisation — DQL classique, inchangée (jamais concernée par ce bug). */
    private function searchWithoutDistance(?string $city, ?string $query, bool $onDutyOnly, bool $openNowOnly, int $page): PharmacySearchResult
    {
        $qb = $this->repository->createQueryBuilder('p')
            ->innerJoin('p.city', 'c')
            ->andWhere('p.status = :status')
            ->setParameter('status', PharmacyStatus::ACTIVE)
            ->orderBy('p.name', 'ASC');

        if ($city !== null && $city !== '') {
            $qb->andWhere('c.slug = :citySlug')->setParameter('citySlug', $city);
        }
        if ($query !== null && $query !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :q OR LOWER(p.addressRaw) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($query).'%');
        }
        if ($onDutyOnly) {
            $qb->andWhere('p.isOnDutyNow = true');
        }
        if ($openNowOnly) {
            $qb->andWhere('p.isOpenNow = true');
        }

        $qb->setFirstResult(($page - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE);
        $items = $qb->getQuery()->getResult();

        return new PharmacySearchResult($items, [], \count($items));
    }
}