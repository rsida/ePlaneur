<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Back-office (roadmap step 3a): the committee may open the administration.
 */
final class Version20261005090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grant ADMIN_ACCESS to the committee';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE app_group
            SET permissions = JSON_ARRAY_APPEND(permissions, '$', 'ADMIN_ACCESS')
            WHERE code = 'committee' AND NOT JSON_CONTAINS(permissions, '"ADMIN_ACCESS"')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE app_group
            SET permissions = JSON_REMOVE(permissions, JSON_UNQUOTE(JSON_SEARCH(permissions, 'one', 'ADMIN_ACCESS')))
            WHERE code = 'committee' AND JSON_CONTAINS(permissions, '"ADMIN_ACCESS"')
            SQL);
    }
}
