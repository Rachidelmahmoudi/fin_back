<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Security\Voter\PharmacyVoter;
use App\State\PharmacyCollectionProvider;
use App\State\PharmacyItemProvider;
use App\State\PharmacyPatchProcessor;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * DTO-first (Phase 3, §3.2) : ne mappe PAS directement l'entité Doctrine.
 * Champs volontairement absents ici et jamais exposés à l'app mobile :
 * legacyReference (donnée d'import interne, sans valeur pour le client).
 *
 * Sécurité (§18) : PATCH protégé par le PharmacyVoter — un pharmacien ne
 * peut modifier que sa propre pharmacie, jamais par un simple id dans l'URL.
 */
#[ApiResource(
    shortName: 'Pharmacy',
    operations: [
        new GetCollection(
            uriTemplate: '/pharmacies',
            provider: PharmacyCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/pharmacies/{id}',
            provider: PharmacyItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/pharmacies/{id}',
            security: "is_granted('".PharmacyVoter::EDIT."', object)",
            provider: PharmacyItemProvider::class,
            processor: PharmacyPatchProcessor::class,
            deserialize: false,
        ),
    ],
    normalizationContext: ['groups' => ['pharmacy:read']],
    denormalizationContext: ['groups' => ['pharmacy:write']],
)]
final class PharmacyResource
{
    #[Groups(['pharmacy:read'])]
    public string $id;

    #[Groups(['pharmacy:read'])]
    public string $slug;

    #[Groups(['pharmacy:read', 'pharmacy:write'])]
    public string $name;

    #[Groups(['pharmacy:read', 'pharmacy:write'])]
    public string $addressRaw;

    #[Groups(['pharmacy:read', 'pharmacy:write'])]
    public ?string $district = null;

    #[Groups(['pharmacy:read'])]
    public string $cityName;

    #[Groups(['pharmacy:read'])]
    public string $citySlug;

    #[Groups(['pharmacy:read'])]
    public ?float $lat = null;

    #[Groups(['pharmacy:read'])]
    public ?float $lng = null;

    /** Distance en km par rapport au point de recherche — calculée à la volée, pas stockée. */
    #[Groups(['pharmacy:read'])]
    public ?float $distanceKm = null;

    #[Groups(['pharmacy:read', 'pharmacy:write'])]
    public ?string $phone = null;

    #[Groups(['pharmacy:read', 'pharmacy:write'])]
    public ?string $email = null;

    #[Groups(['pharmacy:read', 'pharmacy:write'])]
    public ?string $website = null;

    #[Groups(['pharmacy:read'])]
    public ?string $photoUrl = null;

    #[Groups(['pharmacy:read'])]
    public bool $isVerified = false;

    /** Calculé en temps réel par PharmacyAvailabilityService, jamais éditable directement. */
    #[Groups(['pharmacy:read'])]
    public bool $isOpenNow = false;

    #[Groups(['pharmacy:read'])]
    public bool $isOnDutyNow = false;

    /**
     * @var string[]
     */
    #[Groups(['pharmacy:read'])]
    public array $services = [];
}
