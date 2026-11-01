<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\State\MedicineCollectionProvider;
use App\State\MedicineItemProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'Medicine',
    operations: [
        new GetCollection(uriTemplate: '/medicines', provider: MedicineCollectionProvider::class),
        new Get(uriTemplate: '/medicines/{id}', provider: MedicineItemProvider::class),
    ],
    normalizationContext: ['groups' => ['medicine:read']],
)]
final class MedicineResource
{
    #[Groups(['medicine:read'])]
    public string $id;

    #[Groups(['medicine:read'])]
    public string $slug;

    #[Groups(['medicine:read'])]
    public string $name;

    #[Groups(['medicine:read'])]
    public ?string $dciName = null;

    #[Groups(['medicine:read'])]
    public ?float $dosageValue = null;

    #[Groups(['medicine:read'])]
    public ?string $dosageUnit = null;

    #[Groups(['medicine:read'])]
    public ?string $form = null;

    #[Groups(['medicine:read'])]
    public ?string $presentation = null;

    #[Groups(['medicine:read'])]
    public ?string $manufacturer = null;

    #[Groups(['medicine:read'])]
    public ?string $therapeuticClass = null;

    #[Groups(['medicine:read'])]
    public ?string $tableau = null;

    #[Groups(['medicine:read'])]
    public ?string $indications = null;

    #[Groups(['medicine:read'])]
    public ?string $contraindications = null;

    #[Groups(['medicine:read'])]
    public ?string $precautions = null;

    /** Prix affiché : priorité au prix officiel CNSS (ppvCnss) si disponible, sinon prix medicament.ma. */
    #[Groups(['medicine:read'])]
    public ?float $price = null;

    #[Groups(['medicine:read'])]
    public bool $isCnssReimbursed = false;

    #[Groups(['medicine:read'])]
    public ?float $reimbursementRate = null;

    #[Groups(['medicine:read'])]
    public bool $needsReview = false;
}
