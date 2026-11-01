<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WalletRechargeRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: WalletRechargeRequestRepository::class)]
#[ORM\Table(name: 'wallet_recharge_request')]
#[ORM\Index(name: 'idx_recharge_status', columns: ['status'])]
class WalletRechargeRequest
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'string', enumType: WalletRechargeMethod::class)]
    private WalletRechargeMethod $method;

    /** Montant ANNONCÉ par l'utilisateur — l'admin peut créditer un montant différent après vérification du reçu réel. */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $announcedAmount;

    /** Référence libre : numéro de transaction PayPal, référence de virement, etc. — aide l'admin à retrouver le reçu WhatsApp correspondant. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'string', enumType: WalletRechargeStatus::class)]
    private WalletRechargeStatus $status = WalletRechargeStatus::PENDING;

    /** Renseigné par l'admin au moment de la confirmation — trace le montant réellement crédité, qui peut différer de announcedAmount. */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $confirmedAmount = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $processedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, WalletRechargeMethod $method, string $announcedAmount)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->method = $method;
        $this->announcedAmount = $announcedAmount;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getMethod(): WalletRechargeMethod
    {
        return $this->method;
    }

    public function getAnnouncedAmount(): string
    {
        return $this->announcedAmount;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getStatus(): WalletRechargeStatus
    {
        return $this->status;
    }

    public function confirm(User $admin, string $confirmedAmount): static
    {
        $this->status = WalletRechargeStatus::CONFIRMED;
        $this->confirmedAmount = $confirmedAmount;
        $this->processedBy = $admin;
        $this->processedAt = new \DateTimeImmutable();

        return $this;
    }

    public function reject(User $admin): static
    {
        $this->status = WalletRechargeStatus::REJECTED;
        $this->processedBy = $admin;
        $this->processedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
