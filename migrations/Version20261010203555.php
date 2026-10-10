<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The airfields of the Condor landscapes (app:condor:airfields).
 */
final class Version20261010203555 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Airfields of the Condor landscapes, for the glide cone of the flight viewer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE airfield (id INT AUTO_INCREMENT NOT NULL, landscape VARCHAR(60) NOT NULL, name VARCHAR(60) NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, altitude DOUBLE PRECISION NOT NULL, length INT NOT NULL, INDEX airfield_landscape (landscape), UNIQUE INDEX airfield_landscape_name (landscape, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE airfield');
    }
}
