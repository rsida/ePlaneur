<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Name shown to the others (pseudonyms for minors): name, first name or pseudonym.
 */
final class Version20261015112920 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accounts: the name the others see, and a pseudonym';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD name_shown VARCHAR(20) DEFAULT \'name\' NOT NULL, ADD pseudonym VARCHAR(30) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E93654B190 ON app_user (pseudonym)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_88BDF3E93654B190 ON app_user');
        $this->addSql('ALTER TABLE app_user DROP name_shown, DROP pseudonym');
    }
}
