<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\State\DoctorCollectionProvider;
use App\State\DoctorItemProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Lecture seule côté public — la création passe par
 * EstablishmentController (workflow de modération), jamais par un POST
 * directement sur cette ApiResource (§Addendum D).
 * Seuls les Doctor PUBLISHED sont exposés (filtré dans le provider).
 */
#[ApiResource(
    shortName: 'Doctor',
    operations: [
        new GetCollection(uriTemplate: '/doctors', provider: DoctorCollectionProvider::class),
        new Get(uriTemplate: '/doctors/{id}', provider: DoctorItemProvider::class),
    ],
    normalizationContext: ['groups' => ['doctor:read']],
)]
final class DoctorResource
{
    #[Groups(['doctor:read'])]
    public string $id;

    #[Groups(['doctor:read'])]
    public string $slug;

    #[Groups(['doctor:read'])]
    public string $name;

    #[Groups(['doctor:read'])]
    public string $addressRaw;

    #[Groups(['doctor:read'])]
    public string $cityName;

    #[Groups(['doctor:read'])]
    public string $citySlug;

    #[Groups(['doctor:read'])]
    public ?float $lat = null;

    #[Groups(['doctor:read'])]
    public ?float $lng = null;

    #[Groups(['doctor:read'])]
    public ?string $phone = null;

    #[Groups(['doctor:read'])]
    public ?float $consultationPrice = null;

    #[Groups(['doctor:read'])]
    public ?string $bio = null;

    #[Groups(['doctor:read'])]
    public bool $isVerified = false;

    /** @var string[] */
    #[Groups(['doctor:read'])]
    public array $specialties = [];
}
