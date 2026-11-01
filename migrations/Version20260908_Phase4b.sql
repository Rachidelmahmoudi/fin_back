-- FinPharma — Phase 4b : Wallet / PrescriptionRequest / RefreshToken + User
-- PostgreSQL. À faire regénérer proprement via `bin/console doctrine:migrations:diff`
-- une fois le projet Symfony composé — ce fichier sert de référence/vérification manuelle.
-- S'applique après Version20260908_Phase4a.sql.

ALTER TABLE app_user
    ADD COLUMN password VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN decoder_points INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN created_at TIMESTAMP(0) NOT NULL DEFAULT NOW();

-- La valeur DEFAULT '' sur password est temporaire, uniquement pour ne pas
-- casser une migration sur une table déjà peuplée en dev/test ; en
-- production réelle il n'y aura aucune ligne existante à ce stade.
ALTER TABLE app_user ALTER COLUMN password DROP DEFAULT;

CREATE TABLE refresh_token (
    id UUID NOT NULL,
    user_id UUID NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP(0) NOT NULL,
    created_at TIMESTAMP(0) NOT NULL,
    revoked_at TIMESTAMP(0) DEFAULT NULL,
    device_label VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id)
);
CREATE UNIQUE INDEX uniq_refresh_token_hash ON refresh_token (token_hash);
CREATE INDEX idx_refresh_token_hash ON refresh_token (token_hash);
ALTER TABLE refresh_token ADD CONSTRAINT fk_refresh_token_user
    FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE;

CREATE TABLE wallet (
    id UUID NOT NULL,
    owner_id UUID NOT NULL,
    balance NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    hold_balance NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) NOT NULL DEFAULT 'MAD',
    PRIMARY KEY (id)
);
CREATE UNIQUE INDEX uniq_wallet_owner ON wallet (owner_id);
ALTER TABLE wallet ADD CONSTRAINT fk_wallet_owner
    FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE;
-- Défense en profondeur (§WalletService) : la contrainte DB empêche un
-- solde négatif même si un futur appel bypassait le service applicatif.
ALTER TABLE wallet ADD CONSTRAINT chk_wallet_balance_non_negative CHECK (balance >= 0);
ALTER TABLE wallet ADD CONSTRAINT chk_wallet_hold_non_negative CHECK (hold_balance >= 0);

CREATE TABLE wallet_transaction (
    id UUID NOT NULL,
    wallet_id UUID NOT NULL,
    type VARCHAR(20) NOT NULL, -- CREDIT|DEBIT|REFUND|HOLD|RELEASE
    amount NUMERIC(10,2) NOT NULL,
    related_entity_id VARCHAR(36) DEFAULT NULL,
    related_entity_type VARCHAR(60) DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP(0) NOT NULL,
    PRIMARY KEY (id)
);
CREATE INDEX idx_wallet_tx_wallet ON wallet_transaction (wallet_id);
ALTER TABLE wallet_transaction ADD CONSTRAINT fk_wallet_tx_wallet
    FOREIGN KEY (wallet_id) REFERENCES wallet (id);

CREATE TABLE prescription_request (
    id UUID NOT NULL,
    pharmacist_id UUID NOT NULL,
    decoder_id UUID DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT', -- DRAFT|PUBLISHED|ACCEPTED|IN_PROGRESS|COMPLETED|EXPIRED|CANCELLED
    image_url VARCHAR(255) NOT NULL,
    estimated_items_count INTEGER NOT NULL,
    created_at TIMESTAMP(0) NOT NULL,
    accepted_at TIMESTAMP(0) DEFAULT NULL,
    expires_at TIMESTAMP(0) DEFAULT NULL,
    completed_at TIMESTAMP(0) DEFAULT NULL,
    PRIMARY KEY (id)
);
CREATE INDEX idx_prescription_status ON prescription_request (status);
ALTER TABLE prescription_request ADD CONSTRAINT fk_prescription_pharmacist
    FOREIGN KEY (pharmacist_id) REFERENCES app_user (id);
ALTER TABLE prescription_request ADD CONSTRAINT fk_prescription_decoder
    FOREIGN KEY (decoder_id) REFERENCES app_user (id);

-- Index partiel : accélère spécifiquement la requête la plus fréquente de
-- l'app déchiffreur ("liste des demandes disponibles"), sans alourdir
-- inutilement les écritures sur les lignes déjà terminées (COMPLETED/EXPIRED/CANCELLED).
CREATE INDEX idx_prescription_published ON prescription_request (created_at) WHERE status = 'PUBLISHED';

CREATE TABLE prescription_result (
    id UUID NOT NULL,
    request_id UUID NOT NULL,
    items JSON NOT NULL,
    points_awarded INTEGER NOT NULL DEFAULT 10,
    submitted_at TIMESTAMP(0) NOT NULL,
    PRIMARY KEY (id)
);
CREATE UNIQUE INDEX uniq_prescription_result_request ON prescription_result (request_id);
ALTER TABLE prescription_result ADD CONSTRAINT fk_prescription_result_request
    FOREIGN KEY (request_id) REFERENCES prescription_request (id);
