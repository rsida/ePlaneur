<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Presentation of the site for the committee (doc/site-tour.md): its permission, and a link in the
 * main menu shown to the committee only.
 */
final class Version20261015200403 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grant SITE_TOUR to the committee and link /comite/le-site in the main menu';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE app_group
            SET permissions = JSON_ARRAY_APPEND(permissions, '$', 'SITE_TOUR')
            WHERE code = 'committee' AND NOT JSON_CONTAINS(permissions, '"SITE_TOUR"')
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO menu_item (location, label, url, visibility, position, parent_id, page_id)
            SELECT 'main', 'Le nouveau site', '/comite/le-site', 'groups', COALESCE(MAX(position), -1) + 1, NULL, NULL
            FROM menu_item WHERE location = 'main' AND parent_id IS NULL
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO menu_item_allowed_group (menu_item_id, group_id)
            SELECT m.id, g.id FROM menu_item m, app_group g
            WHERE m.location = 'main' AND m.url = '/comite/le-site' AND g.code = 'committee'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM menu_item WHERE location = 'main' AND url = '/comite/le-site'");
        $this->addSql(<<<'SQL'
            UPDATE app_group
            SET permissions = JSON_REMOVE(permissions, JSON_UNQUOTE(JSON_SEARCH(permissions, 'one', 'SITE_TOUR')))
            WHERE code = 'committee' AND JSON_CONTAINS(permissions, '"SITE_TOUR"')
            SQL);
    }
}
