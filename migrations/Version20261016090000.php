<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Links of the "Le Club" mega-menu that led nowhere: anchors that no section of the page carries,
 * "Objectifs du Club" sent to the page of other documents, "Écrire au Club" to an e-mail address
 * while the contact form exists. Only links still holding the old address change.
 */
final class Version20261016090000 extends AbstractMigration
{
    /** Old address => new address, for the links of the main menu and the footer. */
    private const array LINKS = [
        '/le-club/objectifs#brevet' => '/le-club/objectifs/brevet-eplaneur',
        '/le-club/textes-officiels/autres-documents' => '/le-club/objectifs#les-cinq-objectifs-principaux-du-club-eplaneur',
        '/le-club/communication#video' => '/le-club/communication/video-presentation',
        '/le-club/communication#interne' => '/le-club/communication/communication-interne',
        '/le-club/communication#enseignants' => '/le-club/communication/lettre-enseignants',
        '/le-club/activites#reunions' => '/le-club/activites#reunions-du-club',
        '/le-club/activites#actions' => '/le-club/activites#suivi-des-actions',
        '/le-club/activites#ateliers' => '/le-club/activites#ateliers-avec-des-etablissements-d-enseignement',
        'mailto:contact@club.eplaneur.fr' => '/contact',
    ];

    public function getDescription(): string
    {
        return 'Point the links of the "Le Club" mega-menu to pages and sections that exist';
    }

    public function up(Schema $schema): void
    {
        foreach (self::LINKS as $old => $new) {
            $this->addSql('UPDATE menu_item SET url = :new WHERE url = :old', ['old' => $old, 'new' => $new]);
        }
        $this->addSql(<<<'SQL'
            UPDATE menu_item SET description = 'Les cinq objectifs principaux'
            WHERE url = '/le-club/objectifs#les-cinq-objectifs-principaux-du-club-eplaneur' AND description = 'Document PDF'
            SQL);
        $this->addSql("UPDATE menu_item SET description = 'Formulaire de contact' WHERE url = '/contact' AND description = 'Formulaire et courriel'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE menu_item SET description = 'Formulaire et courriel' WHERE url = '/contact' AND description = 'Formulaire de contact'");
        $this->addSql(<<<'SQL'
            UPDATE menu_item SET description = 'Document PDF'
            WHERE url = '/le-club/objectifs#les-cinq-objectifs-principaux-du-club-eplaneur' AND description = 'Les cinq objectifs principaux'
            SQL);
        foreach (self::LINKS as $old => $new) {
            if ('/contact' !== $new) {
                $this->addSql('UPDATE menu_item SET url = :old WHERE url = :new', ['old' => $old, 'new' => $new]);
            }
        }
        // Other links may have led to /contact before: only the mega-menu one goes back
        $this->addSql("UPDATE menu_item SET url = 'mailto:contact@club.eplaneur.fr' WHERE url = '/contact' AND label = 'Écrire au Club'");
    }
}
