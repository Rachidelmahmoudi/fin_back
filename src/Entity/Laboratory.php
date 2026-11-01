<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LaboratoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\ModeratableEstablishment;

/**
 * §7 et §Addendum C/D. Même remarque que Doctor sur le choix de ne pas
 * hériter d'une entité Establishment commune.
 */
#[ORM\Entity(repositoryClass: LaboratoryRepository::class)]
#[ORM\Table(name: 'laboratory')]
#[ORM\UniqueConstraint(name: 'uniq_laboratory_slug', columns: ['slug'])]
class Laboratory implements ModeratableEstablishment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 12)]
    private string $uid;

    #[ORM\Column(length: 220)]
    private string $slug;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $owner = null;

    #[ORM\Column(type: 'string', enumType: EstablishmentStatus::class)]
    private EstablishmentStatus $status = EstablishmentStatus::DRAFT;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $verifiedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: 'text')]
    private string $addressRaw;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $district = null;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(nullable: false)]
    private City $city;

    #[ORM\Column(type: 'decimal', precision: 9, scale: 6, nullable: true)]
    private ?string $lat = null;

    #[ORM\Column(type: 'decimal', precision: 9, scale: 6, nullable: true)]
    private ?string $lng = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(type: 'json')]
    private array $photoUrls = [];

    /** Texte libre ou référence de fichier justificatif — pas de contrainte de format imposée au lancement (§Addendum D). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accreditation = null;

    #[ORM\OneToMany(mappedBy: 'laboratory', targetEntity: LabTest::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $labTests;

    #[ORM\OneToMany(mappedBy: 'laboratory', targetEntity: LaboratoryOpeningHour::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $openingHours;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    public function __construct(string $uid, string $slug, string $name, string $addressRaw, City $city)
    {
        $this->id = Uuid::v7();
        $this->uid = $uid;
        $this->slug = $slug;
        $this->name = $name;
        $this->addressRaw = $addressRaw;
        $this->city = $city;
        $this->labTests = new ArrayCollection();
        $this->openingHours = new ArrayCollection();
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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getStatus(): EstablishmentStatus
    {
        return $this->status;
    }

    public function setStatus(EstablishmentStatus $status): static
    {
        $this->status = $status;
        if ($status === EstablishmentStatus::PUBLISHED && $this->publishedAt === null) {
            $this->publishedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function markVerified(User $admin): static
    {
        $this->isVerified = true;
        $this->verifiedBy = $admin;
        $this->verifiedAt = new \DateTimeImmutable();

        return $this;
    }

    public function setRejectionReason(?string $reason): static
    {
        $this->rejectionReason = $reason;

        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
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

        return $this;
    }

    public function getCity(): City
    {
        return $this->city;
    }

    public function setCity(City $city): static
    {
        $this->city = $city;

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

    /** @return string[] */
    public function getPhotoUrls(): array
    {
        return $this->photoUrls;
    }

    public function setPhotoUrls(array $photoUrls): static
    {
        $this->photoUrls = $photoUrls;

        return $this;
    }

    public function getAccreditation(): ?string
    {
        return $this->accreditation;
    }

    public function setAccreditation(?string $accreditation): static
    {
        $this->accreditation = $accreditation;

        return $this;
    }

    /** @return Collection<int, LabTest> */
    public function getLabTests(): Collection
    {
        return $this->labTests;
    }

    public function addLabTest(LabTest $test): static
    {
        if (!$this->labTests->contains($test)) {
            $this->labTests->add($test);
            $test->setLaboratory($this);
        }

        return $this;
    }

    /** @return Collection<int, LaboratoryOpeningHour> */
    public function getOpeningHours(): Collection
    {
        return $this->openingHours;
    }

    public function addOpeningHour(LaboratoryOpeningHour $hour): static
    {
        if (!$this->openingHours->contains($hour)) {
            $this->openingHours->add($hour);
            $hour->setLaboratory($this);
        }

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

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
