<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DoctorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\ModeratableEstablishment;

/**
 * §Addendum C/D. Alimentée exclusivement via "Ajouter mon établissement" —
 * aucune donnée médecin n'a été fournie au projet, ce n'est pas une
 * limitation du modèle mais un choix assumé (§4 des précisions : "Ce n'est
 * PAS un problème").
 *
 * Ne réutilise pas l'entité Pharmacy ni un mapped-superclass Establishment
 * Doctrine : les deux entités existent déjà séparément (Pharmacy en Phase
 * 4a), et une hiérarchie d'héritage Doctrine (JOINED/STI) introduite après
 * coup sur une table déjà peuplée en prod serait une migration risquée pour
 * un bénéfice de code limité. La cohérence de workflow entre les 3 types
 * vient du service (EstablishmentModerationService) et de l'interface
 * ModeratableEstablishment, pas d'une hiérarchie de classes.
 */
#[ORM\Entity(repositoryClass: DoctorRepository::class)]
#[ORM\Table(name: 'doctor')]
#[ORM\UniqueConstraint(name: 'uniq_doctor_slug', columns: ['slug'])]
class Doctor implements ModeratableEstablishment
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

    /** Motif affiché au propriétaire si status = REJECTED. */
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

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $consultationPrice = null;

    /** Numéro d'ordre des médecins — champ de vérification, pas obligatoire à la soumission (§Addendum D). */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $orderNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\ManyToMany(targetEntity: Specialty::class)]
    #[ORM\JoinTable(name: 'doctor_specialty')]
    private Collection $specialties;

    #[ORM\OneToMany(mappedBy: 'doctor', targetEntity: DoctorOpeningHour::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
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
        $this->specialties = new ArrayCollection();
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

    public function getConsultationPrice(): ?string
    {
        return $this->consultationPrice;
    }

    public function setConsultationPrice(?string $consultationPrice): static
    {
        $this->consultationPrice = $consultationPrice;

        return $this;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(?string $orderNumber): static
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        $this->touch();

        return $this;
    }

    /** @return Collection<int, Specialty> */
    public function getSpecialties(): Collection
    {
        return $this->specialties;
    }

    public function addSpecialty(Specialty $specialty): static
    {
        if (!$this->specialties->contains($specialty)) {
            $this->specialties->add($specialty);
        }

        return $this;
    }

    public function removeSpecialty(Specialty $specialty): static
    {
        $this->specialties->removeElement($specialty);

        return $this;
    }

    /** @return Collection<int, DoctorOpeningHour> */
    public function getOpeningHours(): Collection
    {
        return $this->openingHours;
    }

    public function addOpeningHour(DoctorOpeningHour $hour): static
    {
        if (!$this->openingHours->contains($hour)) {
            $this->openingHours->add($hour);
            $hour->setDoctor($this);
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
