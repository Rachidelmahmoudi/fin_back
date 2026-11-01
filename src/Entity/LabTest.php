<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LabTestRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * §7/§24 du cahier des charges : "analyses proposées, prix des analyses".
 * Texte libre pour `name`/`category` au lancement (pas de nomenclature
 * d'analyses médicales normalisée fournie) — à structurer plus tard si un
 * référentiel commun (LOINC ou équivalent marocain) devient nécessaire.
 */
#[ORM\Entity(repositoryClass: LabTestRepository::class)]
#[ORM\Table(name: 'lab_test')]
class LabTest
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Laboratory::class, inversedBy: 'labTests')]
    #[ORM\JoinColumn(nullable: false)]
    private Laboratory $laboratory;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $price = null;

    public function __construct(Laboratory $laboratory, string $name)
    {
        $this->id = Uuid::v7();
        $this->laboratory = $laboratory;
        $this->name = $name;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;

        return $this;
    }
}
