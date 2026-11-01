<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\State\LaboratoryCollectionProvider;
use App\State\LaboratoryItemProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'Laboratory',
    operations: [
        new GetCollection(uriTemplate: '/laboratories', provider: LaboratoryCollectionProvider::class),
        new Get(uriTemplate: '/laboratories/{id}', provider: LaboratoryItemProvider::class),
    ],
    normalizationContext: ['groups' => ['laboratory:read']],
)]
final class LaboratoryResource
{
    #[Groups(['laboratory:read'])]
    public string $id;

    #[Groups(['laboratory:read'])]
    public string $slug;

    #[Groups(['laboratory:read'])]
    public string $name;

    #[Groups(['laboratory:read'])]
    public string $addressRaw;

    #[Groups(['laboratory:read'])]
    public string $cityName;

    #[Groups(['laboratory:read'])]
    public string $citySlug;

    #[Groups(['laboratory:read'])]
    public ?float $lat = null;

    #[Groups(['laboratory:read'])]
    public ?float $lng = null;

    #[Groups(['laboratory:read'])]
    public ?string $phone = null;

    #[Groups(['laboratory:read'])]
    public bool $isVerified = false;

    /** @var array<int, array{name: string, category: ?string, price: ?float}> */
    #[Groups(['laboratory:read'])]
    public array $labTests = [];
}
