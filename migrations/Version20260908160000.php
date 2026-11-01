<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convertie depuis Version20260908_AskIa.sql (SQL brut) en vraie classe de
 * migration Doctrine — le SQL brut livré initialement n'était pas
 * reconnu par doctrine-migrations-bundle (qui n'exécute que des
 * classes PHP, jamais des fichiers .sql directement).
 */
final class Version20260908160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Converti depuis Version20260908_AskIa.sql';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ask_ia_session (
    id UUID NOT NULL,
    user_id UUID NOT NULL,
    medicine_id UUID NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT \'IN_PROGRESS\', -- IN_PROGRESS|COMPLETED
    qa_history JSON NOT NULL DEFAULT \'[]\',
    conclusion_text TEXT DEFAULT NULL,
    current_question TEXT DEFAULT NULL,
    created_at TIMESTAMP(0) NOT NULL,
    PRIMARY KEY (id)
)');
        $this->addSql('CREATE INDEX idx_ask_ia_user ON ask_ia_session (user_id)');
        $this->addSql('ALTER TABLE ask_ia_session ADD CONSTRAINT fk_ask_ia_user FOREIGN KEY (user_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE ask_ia_session ADD CONSTRAINT fk_ask_ia_medicine FOREIGN KEY (medicine_id) REFERENCES medicine (id)');
    }

    public function down(Schema $schema): void
    {
        throw new \Doctrine\Migrations\Exception\IrreversibleMigration('Migration irréversible — restaurer depuis une sauvegarde si besoin de revenir en arrière.');
    }
}
