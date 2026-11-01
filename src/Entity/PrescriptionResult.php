<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PrescriptionResultRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Résultat soumis par le déchiffreur. `items` référence des Medicine.id
 * (via l'autocomplete médicament demandé §Addendum 2), pas du texte libre :
 * ça garantit que le coût final (itemsCount x 1 DH) et le lien vers le
 * stock/disponibilité pharmacie restent exploitables ensuite.
 */
#[ORM\Entity(repositoryClass: PrescriptionResultRepository::class)]
#[ORM\Table(name: 'prescription_result')]
class PrescriptionResult
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: PrescriptionRequest::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private PrescriptionRequest $request;

    /**
     * @var array<int, array{medicineId: string, medicineName: string, quantity: int|null}>
     * Structuré via l'autocomplete côté client — chaque ligne référence un
     * Medicine.id réel, pas une saisie texte libre.
     */
    #[ORM\Column(type: 'json')]
    private array $items;

    #[ORM\Column(type: 'integer', options: ['default' => 10])]
    private int $pointsAwarded = 10;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $submittedAt;

    /**
     * @param array<int, array{medicineId: string, medicineName: string, quantity: int|null}> $items
     */
    public function __construct(PrescriptionRequest $request, array $items)
    {
        $this->id = Uuid::v7();
        $this->request = $request;
        $this->items = $items;
        $this->submittedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRequest(): PrescriptionRequest
    {
        return $this->request;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getItemsCount(): int
    {
        return \count($this->items);
    }

    /** Coût réel et définitif — c'est CETTE valeur qui déclenche le DEBIT, pas l'estimation de la demande. */
    public function getFinalCost(): string
    {
        return bcmul((string) $this->getItemsCount(), PrescriptionRequest::COST_PER_MEDICINE, 2);
    }

    public function getPointsAwarded(): int
    {
        return $this->pointsAwarded;
    }

    public function getSubmittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }
}
