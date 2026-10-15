<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Notifications of the installable site: what the devices of each account receive.
 */
final class Version20261015101205 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accounts: the notifications their devices receive';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD push_notifications JSON DEFAULT \'["sessions","message","flight_comment"]\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP push_notifications');
    }
}
