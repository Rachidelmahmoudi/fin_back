<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** 0 = lundi ... 6 = dimanche (ISO-8601 minus 1, plus simple à trier). */
#[ORM\Entity]
#[ORM\Table(name: 'pharmacy_opening_hour')]
#[ORM\Index(name: 'idx_poh_pharmacy', columns: ['pharmacy_id'])]
class PharmacyOpeningHour
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Pharmacy::class, inversedBy: 'openingHours')]
    #[ORM\JoinColumn(nullable: false)]
    private Pharmacy $pharmacy;

    #[ORM\Column(type: 'smallint')]
    private int $dayOfWeek;

    #[ORM\Column(type: 'time')]
    private \DateTimeInterface $opensAt;

    #[ORM\Column(type: 'time')]
    private \DateTimeInterface $closesAt;

    public function __construct(Pharmacy $pharmacy, int $dayOfWeek, \DateTimeInterface $opensAt, \DateTimeInterface $closesAt)
    {
        $this->id = Uuid::v7();
        $this->pharmacy = $pharmacy;
        $this->dayOfWeek = $dayOfWeek;
        $this->opensAt = $opensAt;
        $this->closesAt = $closesAt;
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

    public function getDayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function getOpensAt(): \DateTimeInterface
    {
        return $this->opensAt;
    }

    public function getClosesAt(): \DateTimeInterface
    {
        return $this->closesAt;
    }
}
