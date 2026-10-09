<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Settings of the task of a network session (Condor Club page or .fpl file), roadmap step 7g.
 */
final class Version20261009191730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Task settings of network sessions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE network_session ADD task_settings JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE network_session DROP task_settings');
    }
}
