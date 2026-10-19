<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The gliders of Condor (/planeurs) were reached only from the sheet of a glider: "Planeurs de
 * Condor" closes the "Mes vols" column of the "Voler" mega-menu and the "Formation" column of the
 * footer. Each link is added only when its column exists and does not list /planeurs yet.
 */
final class Version20261019090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '"Planeurs de Condor" in the main menu and the footer';
    }

    public function up(Schema $schema): void
    {
        $column = $this->connection->fetchOne(
            "SELECT c.id FROM menu_item c JOIN menu_item v ON c.parent_id = v.id WHERE c.location = 'main' AND c.label = 'Mes vols' AND v.parent_id IS NULL AND v.label = 'Voler'",
        );
        $this->append($column, 'main', 'Fiches et polaires');

        $footer = $this->connection->fetchOne("SELECT id FROM menu_item WHERE location = 'footer' AND parent_id IS NULL AND label = 'Formation'");
        $this->append($footer, 'footer', null);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM menu_item WHERE url = '/planeurs' AND label = 'Planeurs de Condor'");
    }

    private function append(mixed $parent, string $location, ?string $description): void
    {
        if (false === $parent || 0 < (int) $this->connection->fetchOne("SELECT COUNT(*) FROM menu_item WHERE parent_id = ? AND url = '/planeurs'", [$parent])) {
            return;
        }
        $position = 1 + (int) $this->connection->fetchOne('SELECT COALESCE(MAX(position), -1) FROM menu_item WHERE parent_id = ?', [$parent]);
        $this->addSql(
            "INSERT INTO menu_item (location, label, url, description, visibility, position, parent_id) VALUES (:location, 'Planeurs de Condor', '/planeurs', :description, 'public', :position, :parent)",
            ['location' => $location, 'description' => $description, 'position' => $position, 'parent' => $parent],
        );
    }
}
