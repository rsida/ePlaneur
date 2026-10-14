<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Activity of the club (roadmap step 11i): seen by the committee.
 */
final class Version20261014200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grant ACTIVITY_VIEW to the committee';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE app_group
            SET permissions = JSON_ARRAY_APPEND(permissions, '$', 'ACTIVITY_VIEW')
            WHERE code = 'committee' AND NOT JSON_CONTAINS(permissions, '"ACTIVITY_VIEW"')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE app_group
            SET permissions = JSON_REMOVE(permissions, JSON_UNQUOTE(JSON_SEARCH(permissions, 'one', 'ACTIVITY_VIEW')))
            WHERE code = 'committee' AND JSON_CONTAINS(permissions, '"ACTIVITY_VIEW"')
            SQL);
    }
}
