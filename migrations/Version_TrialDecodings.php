<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * §Essai gratuit de déchiffrement — 2 essais par défaut pour TOUT nouveau
 * compte (la valeur ne compte réellement que pour un pharmacien qui
 * publie une demande, mais la coloner par défaut partout évite une
 * logique spéciale à l'inscription — voir User.trialDecodingsRemaining).
 *
 * RENOMME CE FICHIER en VersionYYYYMMDDHHMMSS.php (horodatage postérieur
 * à tes migrations déjà appliquées) avant de lancer `migrate`.
 */
final class VersionTrialDecodings extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute User.trialDecodingsRemaining et PrescriptionRequest.isTrialRequest';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD COLUMN trial_decodings_remaining INTEGER NOT NULL DEFAULT 2');
        $this->addSql('ALTER TABLE prescription_request ADD COLUMN is_trial_request BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP COLUMN trial_decodings_remaining');
        $this->addSql('ALTER TABLE prescription_request DROP COLUMN is_trial_request');
    }
}
