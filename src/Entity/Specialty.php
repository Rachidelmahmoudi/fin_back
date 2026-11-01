<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SpecialtyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Table de référence (cardiologue, dermatologue, généraliste...). Pré-remplie
 * en back-office plutôt que texte libre sur Doctor, pour permettre les pages
 * SEO /medecins/specialite/{specialite} (§9 du cahier des charges).
 */
#[ORM\Entity(repositoryClass: SpecialtyRepository::class)]
#[ORM\Table(name: 'specialty')]
#[ORM\UniqueConstraint(name: 'uniq_specialty_slug', columns: ['slug'])]
class Specialty
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 140)]
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
