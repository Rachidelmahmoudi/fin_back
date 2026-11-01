<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Dénomination Commune Internationale (principe actif) normalisée.
 * Une DCI peut correspondre à plusieurs Medicine (dosages/présentations/génériques).
 * Sert de pivot pour "/medicaments/dci/{slug}" et pour le rapprochement
 * entre sources (medicament.ma "composition" <-> guide CNSS "DCI1").
 */
#[ORM\Entity]
#[ORM\Table(name: 'dci')]
#[ORM\UniqueConstraint(name: 'uniq_dci_slug', columns: ['slug'])]
class Dci
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255, unique: true)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $slug;

    public function __construct(string $name, string $slug)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->slug = $slug;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }
}
