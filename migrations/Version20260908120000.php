<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convertie depuis Version20260908_Phase4a.sql (SQL brut) en vraie classe de
 * migration Doctrine — le SQL brut livré initialement n'était pas
 * reconnu par doctrine-migrations-bundle (qui n'exécute que des
 * classes PHP, jamais des fichiers .sql directement).
 */
final class Version20260908120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Converti depuis Version20260908_Phase4a.sql';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user (
    id UUID NOT NULL,
    email VARCHAR(180) NOT NULL,
    roles JSON NOT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    locale VARCHAR(5) NOT NULL DEFAULT \'fr\',
    PRIMARY KEY (id)
)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON app_user (email)');
        $this->addSql('CREATE TABLE city (
    id UUID NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    region VARCHAR(120) DEFAULT NULL,
    lat NUMERIC(9,6) DEFAULT NULL,
    lng NUMERIC(9,6) DEFAULT NULL,
    is_active BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (id)
)');
        $this->addSql('CREATE UNIQUE INDEX uniq_city_slug ON city (slug)');
        $this->addSql('CREATE TABLE pharmacy (
    id UUID NOT NULL,
    uid VARCHAR(12) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    name VARCHAR(255) NOT NULL,
    address_raw TEXT NOT NULL,
    district VARCHAR(120) DEFAULT NULL,
    city_id UUID NOT NULL,
    lat NUMERIC(9,6) DEFAULT NULL,
    lng NUMERIC(9,6) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    email VARCHAR(180) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT \'PENDING\',
    is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    verified_by_id UUID DEFAULT NULL,
    owner_id UUID DEFAULT NULL,
    pharmacist_name VARCHAR(180) DEFAULT NULL,
    is_open_now BOOLEAN NOT NULL DEFAULT FALSE,
    is_on_duty_now BOOLEAN NOT NULL DEFAULT FALSE,
    legacy_reference VARCHAR(40) DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_pharmacy_city FOREIGN KEY (city_id) REFERENCES city (id),
    CONSTRAINT fk_pharmacy_verified_by FOREIGN KEY (verified_by_id) REFERENCES app_user (id),
    CONSTRAINT fk_pharmacy_owner FOREIGN KEY (owner_id) REFERENCES app_user (id)
)');
        $this->addSql('CREATE UNIQUE INDEX uniq_pharmacy_slug ON pharmacy (slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_pharmacy_uid ON pharmacy (uid)');
        $this->addSql('CREATE INDEX idx_pharmacy_city ON pharmacy (city_id)');
        $this->addSql('CREATE INDEX idx_pharmacy_legacy_ref ON pharmacy (legacy_reference)');
        $this->addSql('CREATE INDEX idx_pharmacy_geo ON pharmacy (lat, lng)');
        $this->addSql('CREATE TABLE pharmacy_opening_hour (
    id UUID NOT NULL,
    pharmacy_id UUID NOT NULL,
    day_of_week SMALLINT NOT NULL,
    opens_at TIME NOT NULL,
    closes_at TIME NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_poh_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pharmacy (id) ON DELETE CASCADE
)');
        $this->addSql('CREATE INDEX idx_poh_pharmacy ON pharmacy_opening_hour (pharmacy_id)');
        $this->addSql('CREATE TABLE pharmacy_duty_period (
    id UUID NOT NULL,
    pharmacy_id UUID NOT NULL,
    starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_pdp_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pharmacy (id) ON DELETE CASCADE
)');
        $this->addSql('CREATE INDEX idx_pdp_pharmacy ON pharmacy_duty_period (pharmacy_id)');
        $this->addSql('CREATE INDEX idx_pdp_window ON pharmacy_duty_period (starts_at, ends_at)');
        $this->addSql('CREATE TABLE dci (
    id UUID NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    PRIMARY KEY (id)
)');
        $this->addSql('CREATE UNIQUE INDEX uniq_dci_name ON dci (name)');
        $this->addSql('CREATE UNIQUE INDEX uniq_dci_slug ON dci (slug)');
        $this->addSql('CREATE TABLE medicine (
    id UUID NOT NULL,
    uid VARCHAR(12) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    dci_id UUID DEFAULT NULL,
    dosage_value NUMERIC(10,2) DEFAULT NULL,
    dosage_unit VARCHAR(20) DEFAULT NULL,
    form VARCHAR(80) DEFAULT NULL,
    presentation VARCHAR(255) DEFAULT NULL,
    manufacturer VARCHAR(180) DEFAULT NULL,
    therapeutic_class VARCHAR(255) DEFAULT NULL,
    atc_code VARCHAR(20) DEFAULT NULL,
    tableau VARCHAR(1) DEFAULT NULL,
    princeps_or_generic VARCHAR(1) DEFAULT NULL,
    status VARCHAR(60) DEFAULT NULL,
    indications TEXT DEFAULT NULL,
    contraindications TEXT DEFAULT NULL,
    precautions TEXT DEFAULT NULL,
    ean13_code VARCHAR(20) DEFAULT NULL,
    price_commercial NUMERIC(10,2) DEFAULT NULL,
    price_hospital_commercial NUMERIC(10,2) DEFAULT NULL,
    ppv_cnss NUMERIC(10,2) DEFAULT NULL,
    ph_cnss NUMERIC(10,2) DEFAULT NULL,
    ppv_br_cnss NUMERIC(10,2) DEFAULT NULL,
    ph_br_cnss NUMERIC(10,2) DEFAULT NULL,
    is_cnss_reimbursed BOOLEAN NOT NULL DEFAULT FALSE,
    reimbursement_rate NUMERIC(4,3) DEFAULT NULL,
    exoneration_rate NUMERIC(4,3) DEFAULT NULL,
    exoneration_eligible BOOLEAN DEFAULT NULL,
    needs_review BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_medicine_dci FOREIGN KEY (dci_id) REFERENCES dci (id)
)');
        $this->addSql('CREATE UNIQUE INDEX uniq_medicine_slug ON medicine (slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_medicine_uid ON medicine (uid)');
        $this->addSql('CREATE UNIQUE INDEX uniq_medicine_ean13 ON medicine (ean13_code)');
        $this->addSql('CREATE INDEX idx_medicine_dci ON medicine (dci_id)');
        $this->addSql('CREATE INDEX idx_medicine_needs_review ON medicine (needs_review)');
        $this->addSql('CREATE TABLE medicine_source_record (
    id UUID NOT NULL,
    medicine_id UUID NOT NULL,
    source VARCHAR(20) NOT NULL, -- MEDICAMENT_MA | CNSS_GUIDE
    external_key VARCHAR(255) NOT NULL,
    raw_payload JSON NOT NULL,
    imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_msr_medicine FOREIGN KEY (medicine_id) REFERENCES medicine (id) ON DELETE CASCADE
)');
        $this->addSql('CREATE UNIQUE INDEX uniq_msr_source_key ON medicine_source_record (source, external_key)');
        $this->addSql('CREATE INDEX idx_msr_medicine ON medicine_source_record (medicine_id)');
        $this->addSql('CREATE TABLE emergency_number (
    id UUID NOT NULL,
    label VARCHAR(120) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    icon VARCHAR(60) DEFAULT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
)');
        $this->addSql('INSERT INTO city (id, name, slug, is_active) VALUES
    (gen_random_uuid(), \'Casablanca\', \'casablanca\', true),
    (gen_random_uuid(), \'Marrakech\', \'marrakech\', true),
    (gen_random_uuid(), \'Rabat\', \'rabat\', true),
    (gen_random_uuid(), \'Fès\', \'fes\', true),
    (gen_random_uuid(), \'Salé\', \'sale\', true),
    (gen_random_uuid(), \'Benslimane\', \'benslimane\', true),
    (gen_random_uuid(), \'Oujda\', \'oujda\', true),
    (gen_random_uuid(), \'Meknès\', \'meknes\', true),
    (gen_random_uuid(), \'Tanger\', \'tanger\', true),
    (gen_random_uuid(), \'Agadir\', \'agadir\', true),
    (gen_random_uuid(), \'Safi\', \'safi\', true),
    (gen_random_uuid(), \'Dakhla\', \'dakhla\', true)
ON CONFLICT (slug) DO NOTHING');
        $this->addSql('INSERT INTO emergency_number (id, label, phone, icon, sort_order) VALUES
    (gen_random_uuid(), \'Police\', \'19\', \'police\', 1),
    (gen_random_uuid(), \'Gendarmerie Royale\', \'177\', \'police\', 2),
    (gen_random_uuid(), \'Protection Civile / Pompiers\', \'15\', \'fire\', 3),
    (gen_random_uuid(), \'SAMU\', \'141\', \'ambulance\', 4)');
    }

    public function down(Schema $schema): void
    {
        throw new \Doctrine\Migrations\Exception\IrreversibleMigration('Migration irréversible — restaurer depuis une sauvegarde si besoin de revenir en arrière.');
    }
}
