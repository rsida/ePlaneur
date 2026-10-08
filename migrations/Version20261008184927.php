<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008184927 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flight viewer: launch of each flight (airborne, aerotow, winch)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE flight ADD launch VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE flight DROP launch');
    }
}
