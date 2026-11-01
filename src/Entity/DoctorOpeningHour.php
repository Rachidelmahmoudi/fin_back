<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DoctorOpeningHourRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctorOpeningHourRepository::class)]
#[ORM\Table(name: 'doctor_opening_hour')]
class DoctorOpeningHour
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Doctor::class, inversedBy: 'openingHours')]
    #[ORM\JoinColumn(nullable: false)]
    private Doctor $doctor;

    /** 1 (lundi) à 7 (dimanche) — cohérent avec DateTimeImmutable::format('N'). */
    #[ORM\Column(type: 'smallint')]
    private int $dayOfWeek;

    #[ORM\Column(type: 'time')]
    private \DateTimeInterface $opensAt;

    #[ORM\Column(type: 'time')]
    private \DateTimeInterface $closesAt;

    public function __construct(Doctor $doctor, int $dayOfWeek, \DateTimeInterface $opensAt, \DateTimeInterface $closesAt)
    {
        $this->id = Uuid::v7();
        $this->doctor = $doctor;
        $this->dayOfWeek = $dayOfWeek;
        $this->opensAt = $opensAt;
        $this->closesAt = $closesAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDoctor(): Doctor
    {
        return $this->doctor;
    }

    public function setDoctor(Doctor $doctor): static
    {
        $this->doctor = $doctor;

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
