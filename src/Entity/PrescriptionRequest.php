<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PrescriptionRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PrescriptionRequestRepository::class)]
#[ORM\Table(name: 'prescription_request')]
#[ORM\Index(name: 'idx_prescription_status', columns: ['status'])]
class PrescriptionRequest
{
    /** Fenêtre d'acceptation avant expiration automatique (§26). */
    public const ACCEPTANCE_WINDOW_SECONDS = 60;

    /** Coût par médicament identifié (§26/§27). */
    public const COST_PER_MEDICINE = '1.00';

    /** Solde minimum requis pour publier (§27). */
    public const MIN_BALANCE_TO_PUBLISH = '5.00';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $pharmacist;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $decoder = null;

    #[ORM\Column(type: 'string', enumType: PrescriptionRequestStatus::class)]
    private PrescriptionRequestStatus $status = PrescriptionRequestStatus::DRAFT;

    #[ORM\Column(length: 255)]
    private string $imageUrl;

    /**
     * Estimation au moment de la publication (saisie par le pharmacien ou
     * détectée automatiquement plus tard) — sert à calculer le HOLD à
     * l'acceptation. Le coût réel et définitif vient de
     * PrescriptionResult::getItemsCount() à la validation.
     */
    #[ORM\Column(type: 'integer')]
    private int $estimatedItemsCount;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isTrialRequest = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct(User $pharmacist, string $imageUrl, int $estimatedItemsCount)
    {
        $this->id = Uuid::v7();
        $this->pharmacist = $pharmacist;
        $this->imageUrl = $imageUrl;
        $this->estimatedItemsCount = $estimatedItemsCount;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPharmacist(): User
    {
        return $this->pharmacist;
    }

    public function getDecoder(): ?User
    {
        return $this->decoder;
    }

    /** @internal utilisé uniquement par PrescriptionMatchingService après l'UPDATE atomique */
    public function _internalMarkAccepted(User $decoder, \DateTimeImmutable $now): static
    {
        $this->decoder = $decoder;
        $this->status = PrescriptionRequestStatus::ACCEPTED;
        $this->acceptedAt = $now;
        $this->expiresAt = $now->modify(sprintf('+%d seconds', self::ACCEPTANCE_WINDOW_SECONDS));

        return $this;
    }

    public function getStatus(): PrescriptionRequestStatus
    {
        return $this->status;
    }

    public function setStatus(PrescriptionRequestStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function isTrialRequest(): bool
    {
        return $this->isTrialRequest;
    }

    public function markAsTrialRequest(): static
    {
        $this->isTrialRequest = true;

        return $this;
    }

    public function getEstimatedItemsCount(): int
    {
        return $this->estimatedItemsCount;
    }

    public function getEstimatedCost(): string
    {
        return bcmul((string) $this->estimatedItemsCount, self::COST_PER_MEDICINE, 2);
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->status === PrescriptionRequestStatus::ACCEPTED
            && $this->expiresAt !== null
            && $this->expiresAt < $now;
    }

    public function markCompleted(\DateTimeImmutable $now): static
    {
        $this->status = PrescriptionRequestStatus::COMPLETED;
        $this->completedAt = $now;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
