<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Une ligne d'audit par (Medicine, source) — garde le payload brut tel
 * qu'importé pour permettre de ré-auditer un désagrément de prix/champ
 * entre sources, ou de ré-importer proprement si le mapping évolue.
 * Voir Addendum §B pour la justification de ce pattern plutôt qu'une
 * fusion silencieuse en une seule ligne.
 */
#[ORM\Entity]
#[ORM\Table(name: 'medicine_source_record')]
#[ORM\UniqueConstraint(name: 'uniq_msr_source_key', columns: ['source', 'external_key'])]
#[ORM\Index(name: 'idx_msr_medicine', columns: ['medicine_id'])]
class MedicineSourceRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Medicine::class, inversedBy: 'sourceRecords')]
    #[ORM\JoinColumn(nullable: false)]
    private Medicine $medicine;

    #[ORM\Column(type: 'string', enumType: MedicineSource::class)]
    private MedicineSource $source;

    /** URL medicament.ma ou code EAN13 CNSS selon la source. */
    #[ORM\Column(length: 255)]
    private string $externalKey;

    #[ORM\Column(type: 'json')]
    private array $rawPayload;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $importedAt;

    public function __construct(Medicine $medicine, MedicineSource $source, string $externalKey, array $rawPayload)
    {
        $this->id = Uuid::v7();
        $this->medicine = $medicine;
        $this->source = $source;
        $this->externalKey = $externalKey;
        $this->rawPayload = $rawPayload;
        $this->importedAt = new \DateTimeImmutable();
    }

    /** Met à jour la ligne lors d'un ré-import (même source + même clé externe). */
    public function refresh(Medicine $medicine, array $rawPayload): static
    {
        $this->medicine = $medicine;
        $this->rawPayload = $rawPayload;
        $this->importedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getMedicine(): Medicine
    {
        return $this->medicine;
    }

    public function setMedicine(Medicine $medicine): static
    {
        $this->medicine = $medicine;
        return $this;
    }

    public function getSource(): MedicineSource
    {
        return $this->source;
    }

    public function getExternalKey(): string
    {
        return $this->externalKey;
    }

    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }

    public function getImportedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }
}
