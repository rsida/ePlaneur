<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008205729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flight viewer: public flights chosen by their pilots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE flight ADD public TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE flight DROP public');
    }
}
