-- FinPharma — Phase 4c-bis : recharge wallet manuelle (PayPal/virement + reçu WhatsApp)
CREATE TABLE wallet_recharge_request (
    id UUID NOT NULL,
    user_id UUID NOT NULL,
    method VARCHAR(20) NOT NULL, -- PAYPAL|BANK_TRANSFER
    announced_amount NUMERIC(10,2) NOT NULL,
    reference VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING', -- PENDING|CONFIRMED|REJECTED
    confirmed_amount NUMERIC(10,2) DEFAULT NULL,
    processed_by_id UUID DEFAULT NULL,
    processed_at TIMESTAMP(0) DEFAULT NULL,
    created_at TIMESTAMP(0) NOT NULL,
    PRIMARY KEY (id)
);
CREATE INDEX idx_recharge_status ON wallet_recharge_request (status);
ALTER TABLE wallet_recharge_request ADD CONSTRAINT fk_recharge_user FOREIGN KEY (user_id) REFERENCES app_user (id);
ALTER TABLE wallet_recharge_request ADD CONSTRAINT fk_recharge_processed_by FOREIGN KEY (processed_by_id) REFERENCES app_user (id);
