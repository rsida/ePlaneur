<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009072104 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Member space: Condor identity, e-mail preferences and privacy choices of accounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD condor_cn VARCHAR(10) DEFAULT NULL, ADD condor_pilot_name VARCHAR(100) DEFAULT NULL, ADD condor_versions JSON DEFAULT \'[]\' NOT NULL, ADD notifications JSON DEFAULT \'["flight_comment","message","renewal"]\' NOT NULL, ADD flights_public_by_default TINYINT DEFAULT 0 NOT NULL, ADD in_ranking TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP condor_cn, DROP condor_pilot_name, DROP condor_versions, DROP notifications, DROP flights_public_by_default, DROP in_ranking');
    }
}
