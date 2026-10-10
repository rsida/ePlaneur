<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261010072924 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Gliders of Condor: sheet and polar';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE glider (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(40) NOT NULL, model VARCHAR(120) NOT NULL, span DOUBLE PRECISION NOT NULL, wing_area DOUBLE PRECISION NOT NULL, length DOUBLE PRECISION DEFAULT NULL, empty_mass DOUBLE PRECISION NOT NULL, max_mass DOUBLE PRECISION NOT NULL, water_ballast INT NOT NULL, min_wing_loading DOUBLE PRECISION NOT NULL, max_wing_loading DOUBLE PRECISION NOT NULL, max_speed DOUBLE PRECISION NOT NULL, maneuvering_speed DOUBLE PRECISION DEFAULT NULL, min_speed DOUBLE PRECISION DEFAULT NULL, min_sink DOUBLE PRECISION DEFAULT NULL, best_glide DOUBLE PRECISION DEFAULT NULL, best_glide_speed DOUBLE PRECISION DEFAULT NULL, handicap INT DEFAULT NULL, engine VARCHAR(120) DEFAULT NULL, polar JSON NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_5780508A5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE glider');
    }
}
