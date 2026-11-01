<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PharmacyResource;
use App\Entity\Pharmacy;
use App\Service\Search\PharmacySearchService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * GET /api/v1/pharmacies — branché sur PharmacySearchService (Phase 3, §3.2)
 * plutôt que sur une simple pagination Doctrine brute, pour supporter les
 * filtres vus dans le Figma (§Addendum 2 — écran Filtres) : ville, secteur,
 * distance, heures d'ouverture, garde.
 */
final class PharmacyCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly PharmacySearchService $searchService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $request = $this->requestStack->getCurrentRequest();

        $results = $this->searchService->search(
            city: $request?->query->get('city'),
            query: $request?->query->get('q'),
            lat: $request?->query->get('lat') !== null ? (float) $request->query->get('lat') : null,
            lng: $request?->query->get('lng') !== null ? (float) $request->query->get('lng') : null,
            radiusKm: $request?->query->get('radiusKm') !== null ? (float) $request->query->get('radiusKm') : null,
            onDutyOnly: $request?->query->getBoolean('onDuty', false) ?? false,
            openNowOnly: $request?->query->getBoolean('openNow', false) ?? false,
            medicineId: $request?->query->get('medicineId'),
            page: (int) ($request?->query->get('page', 1) ?? 1),
        );

        return array_map(
            fn (Pharmacy $pharmacy) => PharmacyResourceMapper::map($pharmacy, $results->distances[$pharmacy->getId()->toRfc4122()] ?? null),
            $results->items,
        );
    }
}
