<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WalletRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * §27 du cahier des charges. Le solde n'est JAMAIS écrit directement par un
 * appelant : setBalance() est privé à dessein. Seul WalletService (Phase 4b,
 * dans une transaction DB + verrou) est autorisé à faire évoluer le solde,
 * via applyTransaction(), pour garantir qu'aucun débit ne peut faire passer
 * le solde sous zéro et que chaque mouvement est tracé dans
 * WalletTransaction.
 */
#[ORM\Entity(repositoryClass: WalletRepository::class)]
#[ORM\Table(name: 'wallet')]
class Wallet
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $owner;

    /** Solde disponible et dépensable. */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $balance = '0.00';

    /**
     * Montant "réservé" par des demandes de déchiffrement ACCEPTED en
     * attente de validation (§Addendum 2 — débit à la validation, pas à
     * la publication). Ne réduit pas balance tant que non débité, mais
     * WalletService::getSpendable() = balance - holdBalance, pour éviter
     * qu'un pharmacien publie plusieurs demandes au-delà de ce qu'il peut
     * couvrir si elles sont toutes acceptées en même temps.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $holdBalance = '0.00';

    #[ORM\Column(length: 3, options: ['default' => 'MAD'])]
    private string $currency = 'MAD';

    public function __construct(User $owner)
    {
        $this->id = Uuid::v7();
        $this->owner = $owner;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getBalance(): string
    {
        return $this->balance;
    }

    public function getHoldBalance(): string
    {
        return $this->holdBalance;
    }

    /** Solde réellement disponible à la dépense (hors montants réservés). */
    public function getSpendableBalance(): string
    {
        return bcsub($this->balance, $this->holdBalance, 2);
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @internal Réservé à WalletService. Ne pas appeler depuis un
     * controller/command directement — passer par WalletService::credit()/
     * debit()/hold()/release() qui journalisent dans WalletTransaction.
     */
    public function _internalAdjustBalance(string $delta): static
    {
        $this->balance = bcadd($this->balance, $delta, 2);

        return $this;
    }

    /** @internal Réservé à WalletService. */
    public function _internalAdjustHold(string $delta): static
    {
        $this->holdBalance = bcadd($this->holdBalance, $delta, 2);

        return $this;
    }
}
