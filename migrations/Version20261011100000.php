<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma canonique des médicaments : colonnes nécessaires à la
 * réconciliation des 3 sources (guide Excel, guide PDF, medicament.ma).
 * `form` passe à 255 car des formes galéniques longues dépassaient 80.
 */
final class Version20261011100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'medicine : brand_name, pack_quantity, match_key, review_reasons ; form -> VARCHAR(255)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE medicine ADD brand_name VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE medicine ADD pack_quantity INT DEFAULT NULL');
        $this->addSql('ALTER TABLE medicine ADD match_key VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE medicine ADD review_reasons JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE medicine ALTER COLUMN form TYPE VARCHAR(255)');
        $this->addSql('CREATE INDEX idx_medicine_match_key ON medicine (match_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_medicine_match_key');
        $this->addSql('ALTER TABLE medicine DROP brand_name');
        $this->addSql('ALTER TABLE medicine DROP pack_quantity');
        $this->addSql('ALTER TABLE medicine DROP match_key');
        $this->addSql('ALTER TABLE medicine DROP review_reasons');
    }
}
