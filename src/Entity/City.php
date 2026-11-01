<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CityRepository::class)]
#[ORM\Table(name: 'city')]
#[ORM\UniqueConstraint(name: 'uniq_city_slug', columns: ['slug'])]
class City
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 140)]
    private string $slug;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(type: 'decimal', precision: 9, scale: 6, nullable: true)]
    private ?string $lat = null;

    #[ORM\Column(type: 'decimal', precision: 9, scale: 6, nullable: true)]
    private ?string $lng = null;

    /**
     * false = ville existante en base mais pas encore affichée publiquement
     * (pas assez de contenu/établissements). Permet le SEO progressif sans
     * pages pauvres (§11 cahier des charges).
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isActive = false;

    /**
     * @var Collection<int, Pharmacy>
     */
    #[ORM\OneToMany(mappedBy: 'city', targetEntity: Pharmacy::class)]
    private Collection $pharmacies;

    public function __construct(string $name, string $slug)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->slug = $slug;
        $this->pharmacies = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): static
    {
        $this->region = $region;
        return $this;
    }

    public function getLat(): ?string
    {
        return $this->lat;
    }

    public function setLat(?string $lat): static
    {
        $this->lat = $lat;
        return $this;
    }

    public function getLng(): ?string
    {
        return $this->lng;
    }

    public function setLng(?string $lng): static
    {
        $this->lng = $lng;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }
}
