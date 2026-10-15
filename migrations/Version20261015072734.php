<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Session reminders and results by e-mail (roadmap step 11g): what was sent for each session.
 */
final class Version20261015072734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Network sessions: when the reminder and the results were e-mailed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE network_session ADD reminder_sent_at DATETIME DEFAULT NULL, ADD results_sent_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE network_session DROP reminder_sent_at, DROP results_sent_at');
    }
}
