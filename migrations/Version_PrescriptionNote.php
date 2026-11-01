<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * §Demande de déchiffrement — note optionnelle du pharmacien pour cibler
 * quels médicaments de l'ordonnance il veut voir déchiffrés (ex: "juste
 * le 1 et le 2", "le 4ème seulement").
 *
 * RENOMME CE FICHIER en VersionYYYYMMDDHHMMSS.php (le format que Doctrine
 * exige) avec un horodatage POSTÉRIEUR à toutes tes migrations déjà
 * appliquées, sinon `doctrine:migrations:migrate` ne la verra pas comme
 * "nouvelle" - voir la mésaventure qu'on a eue sur les migrations plus
 * tôt dans le projet. Vérifie avec `bin/console doctrine:migrations:status`
 * après l'avoir renommée.
 */
final class VersionPrescriptionNote extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute PrescriptionRequest.note (texte libre optionnel du pharmacien)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prescription_request ADD COLUMN note TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prescription_request DROP COLUMN note');
    }
}
