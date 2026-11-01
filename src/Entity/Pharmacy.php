<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PharmacyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PharmacyRepository::class)]
#[ORM\Table(name: 'pharmacy')]
#[ORM\UniqueConstraint(name: 'uniq_pharmacy_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_pharmacy_city', columns: ['city_id'])]
#[ORM\Index(name: 'idx_pharmacy_legacy_ref', columns: ['legacy_reference'])]
#[ORM\Index(name: 'idx_pharmacy_geo', columns: ['lat', 'lng'])]
class Pharmacy
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    /** Court identifiant utilisé dans les URLs SEO: /pharmacies/{slug}-{uid} */
    #[ORM\Column(length: 12, unique: true)]
    private string $uid;

    #[ORM\Column(length: 220, unique: true)]
    private string $slug;

    #[ORM\Column(length: 255)]
    private string $name;

    // ---- Adresse / géoloc (disponible aujourd'hui, via pharmacies.json) ----
    #[ORM\Column(type: 'text')]
    private string $addressRaw;

    /** Nullable: pas dans le JSON source, à enrichir plus tard (édition pharmacien / back-office). */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $district = null;

    #[ORM\ManyToOne(targetEntity: City::class, inversedBy: 'pharmacies')]
    #[ORM\JoinColumn(nullable: false)]
    private City $city;

    #[ORM\Column(type: 'decimal', precision: 9, scale: 6, nullable: true)]
    private ?string $lat = null;

    #[ORM\Column(type: 'decimal', precision: 9, scale: 6, nullable: true)]
    private ?string $lng = null;

    // ---- Contact ----
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    // ---- Champs prévus pour le fonctionnement futur (§Addendum C) ----
    #[ORM\Column(type: 'string', enumType: PharmacyStatus::class)]
    private PharmacyStatus $status = PharmacyStatus::PENDING;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $verifiedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $owner = null;

    /** Titulaire affiché publiquement (peut différer du compte "owner"). */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $pharmacistName = null;

    /**
     * Dénormalisé, recalculé périodiquement par PharmacyAvailabilityService
     * à partir des horaires + périodes de garde. Ne PAS écrire directement
     * ailleurs que dans ce service.
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isOpenNow = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isOnDutyNow = false;

    // ---- Traçabilité import ----
    /** ex: "REF235779" — référence du site scrapé annuaire-gratuit.ma, jamais utilisée comme clé primaire. */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $legacyReference = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, PharmacyOpeningHour>
     */
    #[ORM\OneToMany(mappedBy: 'pharmacy', targetEntity: PharmacyOpeningHour::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $openingHours;

    /**
     * @var Collection<int, PharmacyDutyPeriod>
     */
    #[ORM\OneToMany(mappedBy: 'pharmacy', targetEntity: PharmacyDutyPeriod::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $dutyPeriods;

    public function __construct(string $uid, string $slug, string $name, string $addressRaw, City $city)
    {
        $this->id = Uuid::v7();
        $this->uid = $uid;
        $this->slug = $slug;
        $this->name = $name;
        $this->addressRaw = $addressRaw;
        $this->city = $city;
        $this->openingHours = new ArrayCollection();
        $this->dutyPeriods = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUid(): string
    {
        return $this->uid;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        $this->touch();
        return $this;
    }

    public function getAddressRaw(): string
    {
        return $this->addressRaw;
    }

    public function setAddressRaw(string $addressRaw): static
    {
        $this->addressRaw = $addressRaw;
        $this->touch();
        return $this;
    }

    public function getDistrict(): ?string
    {
        return $this->district;
    }

    public function setDistrict(?string $district): static
    {
        $this->district = $district;
        $this->touch();
        return $this;
    }

    public function getCity(): City
    {
        return $this->city;
    }

    public function setCity(City $city): static
    {
        $this->city = $city;
        $this->touch();
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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        $this->touch();
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        $this->touch();
        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;
        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;
        return $this;
    }

    public function getStatus(): PharmacyStatus
    {
        return $this->status;
    }

    public function setStatus(PharmacyStatus $status): static
    {
        $this->status = $status;
        $this->touch();
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function markVerified(User $admin): static
    {
        $this->isVerified = true;
        $this->verifiedAt = new \DateTimeImmutable();
        $this->verifiedBy = $admin;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    public function getPharmacistName(): ?string
    {
        return $this->pharmacistName;
    }

    public function setPharmacistName(?string $pharmacistName): static
    {
        $this->pharmacistName = $pharmacistName;
        return $this;
    }

    public function isOpenNow(): bool
    {
        return $this->isOpenNow;
    }

    public function setIsOpenNow(bool $isOpenNow): static
    {
        $this->isOpenNow = $isOpenNow;
        return $this;
    }

    public function isOnDutyNow(): bool
    {
        return $this->isOnDutyNow;
    }

    public function setIsOnDutyNow(bool $isOnDutyNow): static
    {
        $this->isOnDutyNow = $isOnDutyNow;
        return $this;
    }

    public function getLegacyReference(): ?string
    {
        return $this->legacyReference;
    }

    public function setLegacyReference(?string $legacyReference): static
    {
        $this->legacyReference = $legacyReference;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, PharmacyOpeningHour> */
    public function getOpeningHours(): Collection
    {
        return $this->openingHours;
    }

    public function addOpeningHour(PharmacyOpeningHour $hour): static
    {
        if (!$this->openingHours->contains($hour)) {
            $this->openingHours->add($hour);
            $hour->setPharmacy($this);
        }
        return $this;
    }

    /** @return Collection<int, PharmacyDutyPeriod> */
    public function getDutyPeriods(): Collection
    {
        return $this->dutyPeriods;
    }

    public function addDutyPeriod(PharmacyDutyPeriod $period): static
    {
        if (!$this->dutyPeriods->contains($period)) {
            $this->dutyPeriods->add($period);
            $period->setPharmacy($this);
        }
        return $this;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
