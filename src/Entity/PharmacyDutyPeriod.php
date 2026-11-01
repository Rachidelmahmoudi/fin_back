<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Historique des créneaux de garde d'une pharmacie. Permet à la fois
 * de répondre "est-elle de garde maintenant" (dérivé -> Pharmacy::isOnDutyNow)
 * et "quelles pharmacies étaient/seront de garde à telle date/ville"
 * (utile pour /pharmacies-de-garde/{ville}).
 */
#[ORM\Entity]
#[ORM\Table(name: 'pharmacy_duty_period')]
#[ORM\Index(name: 'idx_pdp_pharmacy', columns: ['pharmacy_id'])]
#[ORM\Index(name: 'idx_pdp_window', columns: ['starts_at', 'ends_at'])]
class PharmacyDutyPeriod
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Pharmacy::class, inversedBy: 'dutyPeriods')]
    #[ORM\JoinColumn(nullable: false)]
    private Pharmacy $pharmacy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $endsAt;

    public function __construct(Pharmacy $pharmacy, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt)
    {
        $this->id = Uuid::v7();
        $this->pharmacy = $pharmacy;
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPharmacy(): Pharmacy
    {
        return $this->pharmacy;
    }

    public function setPharmacy(Pharmacy $pharmacy): static
    {
        $this->pharmacy = $pharmacy;
        return $this;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function isActiveAt(\DateTimeImmutable $moment): bool
    {
        return $moment >= $this->startsAt && $moment <= $this->endsAt;
    }
}
