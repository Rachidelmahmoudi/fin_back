<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Numéros utiles (Police, Pompiers, SAMU...) — écran mobile "Numéros utiles"
 * découvert dans le Figma, sans lien avec le reste du modèle. Géré en back-office.
 */
#[ORM\Entity]
#[ORM\Table(name: 'emergency_number')]
class EmergencyNumber
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 120)]
    private string $label;

    #[ORM\Column(length: 20)]
    private string $phone;

    /** Nom d'icône (référence côté front, ex: "police", "ambulance"). */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $sortOrder = 0;

    public function __construct(string $label, string $phone)
    {
        $this->id = Uuid::v7();
        $this->label = $label;
        $this->phone = $phone;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }
}
