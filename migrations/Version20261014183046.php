<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * "Mon premier vol": the steps a pilot said done.
 */
final class Version20261014183046 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mon premier vol: steps said done by the pilot';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD first_flight_steps JSON DEFAULT \'[]\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP first_flight_steps');
    }
}
