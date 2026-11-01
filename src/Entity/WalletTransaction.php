<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WalletTransactionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * CREDIT/DEBIT/REFUND = mouvements réels sur balance.
 * HOLD/RELEASE = mouvements sur holdBalance uniquement (réservation avant
 * débit effectif), introduits pour le flux "débit à la validation du
 * déchiffreur" confirmé dans l'Addendum 2 :
 *   1. Pharmacien publie -> pas de mouvement, juste vérif balance>=5DH.
 *   2. Déchiffreur accepte -> HOLD (montant estimé = itemsCount x 1 DH).
 *   3. Déchiffreur valide  -> RELEASE du hold + DEBIT réel (montant exact).
 *   4. Expiration/annulation avant validation -> RELEASE seul, aucun DEBIT.
 */
enum WalletTransactionType: string
{
    case CREDIT = 'CREDIT';
    case DEBIT = 'DEBIT';
    case REFUND = 'REFUND';
    case HOLD = 'HOLD';
    case RELEASE = 'RELEASE';
}

#[ORM\Entity(repositoryClass: WalletTransactionRepository::class)]
#[ORM\Table(name: 'wallet_transaction')]
#[ORM\Index(name: 'idx_wallet_tx_wallet', columns: ['wallet_id'])]
class WalletTransaction
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Wallet::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Wallet $wallet;

    #[ORM\Column(type: 'string', enumType: WalletTransactionType::class)]
    private WalletTransactionType $type;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $amount;

    /**
     * Référence libre vers l'entité liée (ex: PrescriptionRequest.id) pour
     * traçabilité, sans FK stricte : une transaction wallet doit rester
     * lisible même si l'entité liée est un jour supprimée/archivée.
     */
    #[ORM\Column(length: 36, nullable: true)]
    private ?string $relatedEntityId = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $relatedEntityType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Wallet $wallet, WalletTransactionType $type, string $amount)
    {
        $this->id = Uuid::v7();
        $this->wallet = $wallet;
        $this->type = $type;
        $this->amount = $amount;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getWallet(): Wallet
    {
        return $this->wallet;
    }

    public function getType(): WalletTransactionType
    {
        return $this->type;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setRelatedEntity(string $type, string $id): static
    {
        $this->relatedEntityType = $type;
        $this->relatedEntityId = $id;

        return $this;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
