<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convertie depuis Version20260908_Phase4c_bis.sql (SQL brut) en vraie classe de
 * migration Doctrine — le SQL brut livré initialement n'était pas
 * reconnu par doctrine-migrations-bundle (qui n'exécute que des
 * classes PHP, jamais des fichiers .sql directement).
 */
final class Version20260908150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Converti depuis Version20260908_Phase4c_bis.sql';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wallet_recharge_request (
    id UUID NOT NULL,
    user_id UUID NOT NULL,
    method VARCHAR(20) NOT NULL, -- PAYPAL|BANK_TRANSFER
    announced_amount NUMERIC(10,2) NOT NULL,
    reference VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT \'PENDING\', -- PENDING|CONFIRMED|REJECTED
    confirmed_amount NUMERIC(10,2) DEFAULT NULL,
    processed_by_id UUID DEFAULT NULL,
    processed_at TIMESTAMP(0) DEFAULT NULL,
    created_at TIMESTAMP(0) NOT NULL,
    PRIMARY KEY (id)
)');
        $this->addSql('CREATE INDEX idx_recharge_status ON wallet_recharge_request (status)');
        $this->addSql('ALTER TABLE wallet_recharge_request ADD CONSTRAINT fk_recharge_user FOREIGN KEY (user_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE wallet_recharge_request ADD CONSTRAINT fk_recharge_processed_by FOREIGN KEY (processed_by_id) REFERENCES app_user (id)');
    }

    public function down(Schema $schema): void
    {
        throw new \Doctrine\Migrations\Exception\IrreversibleMigration('Migration irréversible — restaurer depuis une sauvegarde si besoin de revenir en arrière.');
    }
}
