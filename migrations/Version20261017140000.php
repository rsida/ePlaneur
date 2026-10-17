<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The sessions (agenda, results, replays) and the flights of the members were reached only from the
 * home page, the account or an e-mail: "Voler" of the main menu becomes a mega-menu ("Vols en
 * réseau" with the sessions, "Mes vols") and the footer gets "Sessions et résultats". Left alone
 * when "Voler" already has links under it (edited in the back-office).
 */
final class Version20261017140000 extends AbstractMigration
{
    /** Column => [url, links [label, url, description]] */
    private const array COLUMNS = [
        'Vols en réseau' => ['/voler/vols-reseau', [
            ['Sessions : agenda et résultats', '/sessions', 'Prochains vols, résultats, rejeu'],
            ['Participer à un vol', '/voler/vols-reseau/participer', null],
            ['Prérequis pour voler', '/voler/vols-reseau/prerequis', null],
        ]],
        'Mes vols' => ['/vols', [
            ['Déposer une trace', '/vols', 'Analyse, rejeu 3D, comparaison'],
            ['Mon carnet de vol', '/vols/carnet', null],
            ['Ascendances du club', '/vols/ascendances', 'Les thermiques de tous les vols'],
            ['Records du club', '/vols/records', null],
        ]],
    ];

    public function getDescription(): string
    {
        return 'Mega-menu "Voler" with the sessions and the flights, "Sessions et résultats" in the footer';
    }

    public function up(Schema $schema): void
    {
        $voler = $this->connection->fetchOne("SELECT id FROM menu_item WHERE location = 'main' AND parent_id IS NULL AND label = 'Voler'");
        $empty = false !== $voler && 0 === (int) $this->connection->fetchOne('SELECT COUNT(*) FROM menu_item WHERE parent_id = ?', [$voler]);
        if ($empty) {
            $this->addSql("UPDATE menu_item SET description = 'Voler / Vols en réseau' WHERE id = :id AND description IS NULL", ['id' => $voler]);
            $position = 0;
            foreach (self::COLUMNS as $label => [$url, $links]) {
                $this->addSql(
                    "INSERT INTO menu_item (location, label, url, visibility, position, parent_id) VALUES ('main', :label, :url, 'public', :position, :parent)",
                    ['label' => $label, 'url' => $url, 'position' => $position++, 'parent' => $voler],
                );
                foreach ($links as $index => [$linkLabel, $linkUrl, $description]) {
                    $this->addSql(
                        <<<'SQL'
                            INSERT INTO menu_item (location, label, url, description, visibility, position, parent_id)
                            SELECT 'main', :label, :url, :description, 'public', :position, id FROM menu_item
                            WHERE location = 'main' AND parent_id = :voler AND label = :column
                            SQL,
                        ['label' => $linkLabel, 'url' => $linkUrl, 'description' => $description, 'position' => $index, 'voler' => $voler, 'column' => $label],
                    );
                }
            }
        }

        $footer = $this->connection->fetchOne("SELECT id FROM menu_item WHERE location = 'footer' AND parent_id IS NULL AND label = 'Voler'");
        $listed = false !== $footer && 0 < (int) $this->connection->fetchOne("SELECT COUNT(*) FROM menu_item WHERE parent_id = ? AND url = '/sessions'", [$footer]);
        if (false !== $footer && !$listed) {
            $this->addSql('UPDATE menu_item SET position = position + 1 WHERE parent_id = :footer AND position >= 1', ['footer' => $footer]);
            $this->addSql(
                "INSERT INTO menu_item (location, label, url, visibility, position, parent_id) VALUES ('footer', 'Sessions et résultats', '/sessions', 'public', 1, :footer)",
                ['footer' => $footer],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $footer = $this->connection->fetchOne("SELECT id FROM menu_item WHERE location = 'footer' AND parent_id IS NULL AND label = 'Voler'");
        if (false !== $footer) {
            $this->addSql("DELETE FROM menu_item WHERE parent_id = :footer AND url = '/sessions' AND label = 'Sessions et résultats'", ['footer' => $footer]);
            $this->addSql('UPDATE menu_item SET position = position - 1 WHERE parent_id = :footer AND position > 1', ['footer' => $footer]);
        }
        $voler = $this->connection->fetchOne("SELECT id FROM menu_item WHERE location = 'main' AND parent_id IS NULL AND label = 'Voler'");
        if (false !== $voler) {
            $this->addSql(
                'DELETE c FROM menu_item c JOIN menu_item p ON c.parent_id = p.id WHERE p.parent_id = :voler AND p.label IN (:columns)',
                ['voler' => $voler, 'columns' => array_keys(self::COLUMNS)],
                ['columns' => ArrayParameterType::STRING],
            );
            $this->addSql('DELETE FROM menu_item WHERE parent_id = :voler AND label IN (:columns)', ['voler' => $voler, 'columns' => array_keys(self::COLUMNS)], ['columns' => ArrayParameterType::STRING]);
            $this->addSql("UPDATE menu_item SET description = NULL WHERE id = :id AND description = 'Voler / Vols en réseau'", ['id' => $voler]);
        }
    }
}
