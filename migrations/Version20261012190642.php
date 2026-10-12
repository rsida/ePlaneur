<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012190642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Airspaces of the Condor landscapes, for the flight plan workshop';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE airspace (id INT AUTO_INCREMENT NOT NULL, landscape VARCHAR(60) NOT NULL, name VARCHAR(120) NOT NULL, category VARCHAR(30) NOT NULL, floor VARCHAR(30) NOT NULL, ceiling VARCHAR(30) NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, INDEX airspace_landscape (landscape), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE airspace');
    }
}
