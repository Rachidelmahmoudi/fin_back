<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LaboratoryOpeningHourRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: LaboratoryOpeningHourRepository::class)]
#[ORM\Table(name: 'laboratory_opening_hour')]
class LaboratoryOpeningHour
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Laboratory::class, inversedBy: 'openingHours')]
    #[ORM\JoinColumn(nullable: false)]
    private Laboratory $laboratory;

    #[ORM\Column(type: 'smallint')]
    private int $dayOfWeek;

    #[ORM\Column(type: 'time')]
    private \DateTimeInterface $opensAt;

    #[ORM\Column(type: 'time')]
    private \DateTimeInterface $closesAt;

    public function __construct(Laboratory $laboratory, int $dayOfWeek, \DateTimeInterface $opensAt, \DateTimeInterface $closesAt)
    {
        $this->id = Uuid::v7();
        $this->laboratory = $laboratory;
        $this->dayOfWeek = $dayOfWeek;
        $this->opensAt = $opensAt;
        $this->closesAt = $closesAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getLaboratory(): Laboratory
    {
        return $this->laboratory;
    }

    public function setLaboratory(Laboratory $laboratory): static
    {
        $this->laboratory = $laboratory;

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
