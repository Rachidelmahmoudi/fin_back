<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MedicineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

enum MedicineTableau: string
{
    case A = 'A';
    case B = 'B';
}

enum PrincepsOrGeneric: string
{
    case PRINCEPS = 'P';
    case GENERIC = 'G';
}

/**
 * Entité pivot agrégeant les données de plusieurs sources (medicament.ma +
 * guide CNSS). Tous les champs propres à une seule source sont nullable ;
 * rien n'est écrasé, rien n'est deviné. Voir MedicineSourceRecord pour
 * la traçabilité brute par source, et MedicineMatchingService pour la
 * logique de rapprochement.
 */
#[ORM\Entity(repositoryClass: MedicineRepository::class)]
#[ORM\Table(name: 'medicine')]
#[ORM\UniqueConstraint(name: 'uniq_medicine_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_medicine_dci', columns: ['dci_id'])]
#[ORM\Index(name: 'idx_medicine_ean13', columns: ['ean13_code'])]
#[ORM\Index(name: 'idx_medicine_needs_review', columns: ['needs_review'])]
#[ORM\Index(name: 'idx_medicine_match_key', columns: ['match_key'])]
class Medicine
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 12, unique: true)]
    private string $uid;

    #[ORM\Column(length: 255, unique: true)]
    private string $slug;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: Dci::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Dci $dci = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $dosageValue = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $dosageUnit = null;

    /** ex: comprimé, gélule, sirop, flacon... normalisé depuis "presentation"/"Forme". */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $form = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $presentation = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $manufacturer = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $therapeuticClass = null;

    /** Absent du guide CNSS — seulement fourni par medicament.ma. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $atcCode = null;

    #[ORM\Column(type: 'string', enumType: MedicineTableau::class, nullable: true)]
    private ?MedicineTableau $tableau = null;

    #[ORM\Column(type: 'string', enumType: PrincepsOrGeneric::class, nullable: true)]
    private ?PrincepsOrGeneric $princepsOrGeneric = null;

    /** "Commercialisé", etc. — absent du guide CNSS. */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $indications = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contraindications = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $precautions = null;

    /** Code-barres — pivot le plus fiable, absent du scraping medicament.ma. */
    #[ORM\Column(length: 20, nullable: true, unique: true)]
    private ?string $ean13Code = null;

    // ---- Prix source medicament.ma ----
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $priceCommercial = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $priceHospitalCommercial = null;

    // ---- Prix/remboursement source guide CNSS (source de vérité légale) ----
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $ppvCnss = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $phCnss = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $ppvBrCnss = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $phBrCnss = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isCnssReimbursed = false;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 3, nullable: true)]
    private ?string $reimbursementRate = null;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 3, nullable: true)]
    private ?string $exonerationRate = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $exonerationEligible = null;

    /**
     * true si le MedicineMatchingService n'a pas pu rapprocher automatiquement
     * cette ligne avec une ligne d'une autre source de façon fiable (voir
     * Addendum §B). À traiter en back-office plutôt que de deviner.
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $needsReview = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Nom de marque nettoyé (sans dosage ni forme), ex. "ABIRATERONE GT". */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $brandName = null;

    /** Nombre d'unités du conditionnement (ex. 30 pour "1 BOITE 30 COMPRIME"). */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $packQuantity = null;

    /** Clé de rapprochement marque|dosage|unité|forme|conditionnement (aide au dédoublonnage, non unique). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $matchKey = null;

    /** @var list<string>|null Motifs pour lesquels la fiche demande une relecture. */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $reviewReasons = null;

    /**
     * @var Collection<int, MedicineSourceRecord>
     */
    #[ORM\OneToMany(mappedBy: 'medicine', targetEntity: MedicineSourceRecord::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $sourceRecords;

    public function __construct(string $uid, string $slug, string $name)
    {
        $this->id = Uuid::v7();
        $this->uid = $uid;
        $this->slug = $slug;
        $this->name = $name;
        $this->sourceRecords = new ArrayCollection();
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

    public function getDci(): ?Dci
    {
        return $this->dci;
    }

    public function setDci(?Dci $dci): static
    {
        $this->dci = $dci;
        $this->touch();
        return $this;
    }

    public function getDosageValue(): ?string
    {
        return $this->dosageValue;
    }

    public function setDosageValue(?string $dosageValue): static
    {
        $this->dosageValue = $dosageValue;
        return $this;
    }

    public function getDosageUnit(): ?string
    {
        return $this->dosageUnit;
    }

    public function setDosageUnit(?string $dosageUnit): static
    {
        $this->dosageUnit = $dosageUnit;
        return $this;
    }

    public function getForm(): ?string
    {
        return $this->form;
    }

    public function setForm(?string $form): static
    {
        $this->form = $form;
        return $this;
    }

    public function getPresentation(): ?string
    {
        return $this->presentation;
    }

    public function setPresentation(?string $presentation): static
    {
        $this->presentation = $presentation;
        return $this;
    }

    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?string $manufacturer): static
    {
        $this->manufacturer = $manufacturer;
        return $this;
    }

    public function getTherapeuticClass(): ?string
    {
        return $this->therapeuticClass;
    }

    public function setTherapeuticClass(?string $therapeuticClass): static
    {
        $this->therapeuticClass = $therapeuticClass;
        return $this;
    }

    public function getAtcCode(): ?string
    {
        return $this->atcCode;
    }

    public function setAtcCode(?string $atcCode): static
    {
        $this->atcCode = $atcCode;
        return $this;
    }

    public function getTableau(): ?MedicineTableau
    {
        return $this->tableau;
    }

    public function setTableau(?MedicineTableau $tableau): static
    {
        $this->tableau = $tableau;
        return $this;
    }

    public function getPrincepsOrGeneric(): ?PrincepsOrGeneric
    {
        return $this->princepsOrGeneric;
    }

    public function setPrincepsOrGeneric(?PrincepsOrGeneric $value): static
    {
        $this->princepsOrGeneric = $value;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getIndications(): ?string
    {
        return $this->indications;
    }

    public function setIndications(?string $indications): static
    {
        $this->indications = $indications;
        return $this;
    }

    public function getContraindications(): ?string
    {
        return $this->contraindications;
    }

    public function setContraindications(?string $contraindications): static
    {
        $this->contraindications = $contraindications;
        return $this;
    }

    public function getPrecautions(): ?string
    {
        return $this->precautions;
    }

    public function setPrecautions(?string $precautions): static
    {
        $this->precautions = $precautions;
        return $this;
    }

    public function getEan13Code(): ?string
    {
        return $this->ean13Code;
    }

    public function setEan13Code(?string $ean13Code): static
    {
        $this->ean13Code = $ean13Code;
        return $this;
    }

    public function getPriceCommercial(): ?string
    {
        return $this->priceCommercial;
    }

    public function setPriceCommercial(?string $priceCommercial): static
    {
        $this->priceCommercial = $priceCommercial;
        return $this;
    }

    public function getPriceHospitalCommercial(): ?string
    {
        return $this->priceHospitalCommercial;
    }

    public function setPriceHospitalCommercial(?string $price): static
    {
        $this->priceHospitalCommercial = $price;
        return $this;
    }

    public function getPpvCnss(): ?string
    {
        return $this->ppvCnss;
    }

    public function setPpvCnss(?string $ppvCnss): static
    {
        $this->ppvCnss = $ppvCnss;
        return $this;
    }

    public function getPhCnss(): ?string
    {
        return $this->phCnss;
    }

    public function setPhCnss(?string $phCnss): static
    {
        $this->phCnss = $phCnss;
        return $this;
    }

    public function getPpvBrCnss(): ?string
    {
        return $this->ppvBrCnss;
    }

    public function setPpvBrCnss(?string $value): static
    {
        $this->ppvBrCnss = $value;
        return $this;
    }

    public function getPhBrCnss(): ?string
    {
        return $this->phBrCnss;
    }

    public function setPhBrCnss(?string $value): static
    {
        $this->phBrCnss = $value;
        return $this;
    }

    public function isCnssReimbursed(): bool
    {
        return $this->isCnssReimbursed;
    }

    public function setIsCnssReimbursed(bool $value): static
    {
        $this->isCnssReimbursed = $value;
        return $this;
    }

    public function getReimbursementRate(): ?string
    {
        return $this->reimbursementRate;
    }

    public function setReimbursementRate(?string $value): static
    {
        $this->reimbursementRate = $value;
        return $this;
    }

    public function getExonerationRate(): ?string
    {
        return $this->exonerationRate;
    }

    public function setExonerationRate(?string $value): static
    {
        $this->exonerationRate = $value;
        return $this;
    }

    public function getExonerationEligible(): ?bool
    {
        return $this->exonerationEligible;
    }

    public function setExonerationEligible(?bool $value): static
    {
        $this->exonerationEligible = $value;
        return $this;
    }

    public function needsReview(): bool
    {
        return $this->needsReview;
    }

    public function setNeedsReview(bool $needsReview): static
    {
        $this->needsReview = $needsReview;
        return $this;
    }

    /** @return Collection<int, MedicineSourceRecord> */
    public function getSourceRecords(): Collection
    {
        return $this->sourceRecords;
    }

    public function addSourceRecord(MedicineSourceRecord $record): static
    {
        if (!$this->sourceRecords->contains($record)) {
            $this->sourceRecords->add($record);
            $record->setMedicine($this);
        }
        $this->touch();
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

    public function getBrandName(): ?string { return $this->brandName; }

    public function setBrandName(?string $v): static { $this->brandName = $v; $this->touch(); return $this; }

    public function getPackQuantity(): ?int { return $this->packQuantity; }

    public function setPackQuantity(?int $v): static { $this->packQuantity = $v; $this->touch(); return $this; }

    public function getMatchKey(): ?string { return $this->matchKey; }

    public function setMatchKey(?string $v): static { $this->matchKey = $v; $this->touch(); return $this; }

    /** @return list<string> */
    public function getReviewReasons(): array { return $this->reviewReasons ?? []; }

    /** @param list<string>|null $v */
    public function setReviewReasons(?array $v): static { $this->reviewReasons = $v; $this->touch(); return $this; }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
