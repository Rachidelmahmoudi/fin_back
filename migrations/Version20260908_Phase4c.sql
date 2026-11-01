-- FinPharma — Phase 4c : Doctor / Laboratory / Specialty / LabTest
-- PostgreSQL. À faire regénérer proprement via `bin/console doctrine:migrations:diff`
-- une fois le projet Symfony composé — ce fichier sert de référence/vérification manuelle.
-- S'applique après Version20260908_Phase4b.sql.

CREATE TABLE specialty (
    id UUID NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    PRIMARY KEY (id)
);
CREATE UNIQUE INDEX uniq_specialty_slug ON specialty (slug);

CREATE TABLE doctor (
    id UUID NOT NULL,
    uid VARCHAR(12) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    name VARCHAR(255) NOT NULL,
    owner_id UUID DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', -- DRAFT|PENDING_REVIEW|PUBLISHED|REJECTED|SUSPENDED
    is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    verified_by_id UUID DEFAULT NULL,
    verified_at TIMESTAMP(0) DEFAULT NULL,
    rejection_reason VARCHAR(500) DEFAULT NULL,
    address_raw TEXT NOT NULL,
    district VARCHAR(120) DEFAULT NULL,
    city_id UUID NOT NULL,
    lat NUMERIC(9,6) DEFAULT NULL,
    lng NUMERIC(9,6) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    email VARCHAR(180) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    photo_urls JSON NOT NULL DEFAULT '[]',
    consultation_price NUMERIC(8,2) DEFAULT NULL,
    order_number VARCHAR(60) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    created_at TIMESTAMP(0) NOT NULL,
    updated_at TIMESTAMP(0) NOT NULL,
    published_at TIMESTAMP(0) DEFAULT NULL,
    PRIMARY KEY (id)
);
CREATE UNIQUE INDEX uniq_doctor_slug ON doctor (slug);
CREATE INDEX idx_doctor_status ON doctor (status);
CREATE INDEX idx_doctor_city ON doctor (city_id);
ALTER TABLE doctor ADD CONSTRAINT fk_doctor_owner FOREIGN KEY (owner_id) REFERENCES app_user (id);
ALTER TABLE doctor ADD CONSTRAINT fk_doctor_verified_by FOREIGN KEY (verified_by_id) REFERENCES app_user (id);
ALTER TABLE doctor ADD CONSTRAINT fk_doctor_city FOREIGN KEY (city_id) REFERENCES city (id);

CREATE TABLE doctor_specialty (
    doctor_id UUID NOT NULL,
    specialty_id UUID NOT NULL,
    PRIMARY KEY (doctor_id, specialty_id)
);
ALTER TABLE doctor_specialty ADD CONSTRAINT fk_doctor_specialty_doctor FOREIGN KEY (doctor_id) REFERENCES doctor (id) ON DELETE CASCADE;
ALTER TABLE doctor_specialty ADD CONSTRAINT fk_doctor_specialty_specialty FOREIGN KEY (specialty_id) REFERENCES specialty (id) ON DELETE CASCADE;

CREATE TABLE doctor_opening_hour (
    id UUID NOT NULL,
    doctor_id UUID NOT NULL,
    day_of_week SMALLINT NOT NULL,
    opens_at TIME NOT NULL,
    closes_at TIME NOT NULL,
    PRIMARY KEY (id)
);
ALTER TABLE doctor_opening_hour ADD CONSTRAINT fk_doctor_hour_doctor FOREIGN KEY (doctor_id) REFERENCES doctor (id) ON DELETE CASCADE;

CREATE TABLE laboratory (
    id UUID NOT NULL,
    uid VARCHAR(12) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    name VARCHAR(255) NOT NULL,
    owner_id UUID DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    verified_by_id UUID DEFAULT NULL,
    verified_at TIMESTAMP(0) DEFAULT NULL,
    rejection_reason VARCHAR(500) DEFAULT NULL,
    address_raw TEXT NOT NULL,
    district VARCHAR(120) DEFAULT NULL,
    city_id UUID NOT NULL,
    lat NUMERIC(9,6) DEFAULT NULL,
    lng NUMERIC(9,6) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    email VARCHAR(180) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    photo_urls JSON NOT NULL DEFAULT '[]',
    accreditation VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP(0) NOT NULL,
    updated_at TIMESTAMP(0) NOT NULL,
    published_at TIMESTAMP(0) DEFAULT NULL,
    PRIMARY KEY (id)
);
CREATE UNIQUE INDEX uniq_laboratory_slug ON laboratory (slug);
CREATE INDEX idx_laboratory_status ON laboratory (status);
CREATE INDEX idx_laboratory_city ON laboratory (city_id);
ALTER TABLE laboratory ADD CONSTRAINT fk_laboratory_owner FOREIGN KEY (owner_id) REFERENCES app_user (id);
ALTER TABLE laboratory ADD CONSTRAINT fk_laboratory_verified_by FOREIGN KEY (verified_by_id) REFERENCES app_user (id);
ALTER TABLE laboratory ADD CONSTRAINT fk_laboratory_city FOREIGN KEY (city_id) REFERENCES city (id);

CREATE TABLE lab_test (
    id UUID NOT NULL,
    laboratory_id UUID NOT NULL,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(120) DEFAULT NULL,
    price NUMERIC(8,2) DEFAULT NULL,
    PRIMARY KEY (id)
);
ALTER TABLE lab_test ADD CONSTRAINT fk_lab_test_laboratory FOREIGN KEY (laboratory_id) REFERENCES laboratory (id) ON DELETE CASCADE;

CREATE TABLE laboratory_opening_hour (
    id UUID NOT NULL,
    laboratory_id UUID NOT NULL,
    day_of_week SMALLINT NOT NULL,
    opens_at TIME NOT NULL,
    closes_at TIME NOT NULL,
    PRIMARY KEY (id)
);
ALTER TABLE laboratory_opening_hour ADD CONSTRAINT fk_laboratory_hour_laboratory FOREIGN KEY (laboratory_id) REFERENCES laboratory (id) ON DELETE CASCADE;

-- Pré-remplissage des spécialités courantes (§Addendum D, à compléter en
-- back-office selon les besoins réels observés à l'usage).
INSERT INTO specialty (id, name, slug) VALUES
    (gen_random_uuid(), 'Généraliste', 'generaliste'),
    (gen_random_uuid(), 'Cardiologue', 'cardiologue'),
    (gen_random_uuid(), 'Dermatologue', 'dermatologue'),
    (gen_random_uuid(), 'Pédiatre', 'pediatre'),
    (gen_random_uuid(), 'Gynécologue', 'gynecologue'),
    (gen_random_uuid(), 'Dentiste', 'dentiste'),
    (gen_random_uuid(), 'Ophtalmologue', 'ophtalmologue'),
    (gen_random_uuid(), 'ORL', 'orl'),
    (gen_random_uuid(), 'Psychiatre', 'psychiatre'),
    (gen_random_uuid(), 'Radiologue', 'radiologue');
