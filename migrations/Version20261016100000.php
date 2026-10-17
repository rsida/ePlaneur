<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Links of the main menu and of the footer that still led to a section of the home page, seeded
 * before the pages and features existed: they now open the page or the feature itself. Only links
 * still holding the old address change.
 */
final class Version20261016100000 extends AbstractMigration
{
    /** [location, label, old address, new address] */
    private const array LINKS = [
        ['main', 'Formation', '/#formation', '/formation'],
        ['main', 'Voler', '/#vols', '/voler'],
        ['footer', 'Installer Condor 2', '/#guides', '/formation/bien-demarrer/installer-condor'],
        ['footer', 'Installer Condor 3', '/#guides', '/formation/bien-demarrer/installer-condor'],
        ['footer', 'Parcours guidé', '/#formation', '/parcours'],
        ['footer', 'Vidéos pédagogiques', '/#formation', '/formation/videos-pedagogiques'],
        ['footer', 'Bonnes pratiques', '/#guides', '/formation/bonnes-pratiques'],
        ['footer', 'Vols en réseau', '/#vols', '/voler/vols-reseau'],
        ['footer', 'Participer à un vol', '/#vols', '/voler/vols-reseau/participer'],
        ['footer', 'Prérequis pour voler', '/#vols', '/voler/vols-reseau/prerequis'],
        ['footer', 'Déposer une trace', '/#carnet', '/vols'],
        ['footer', 'Créer mon carnet', '/#carnet', '/vols/carnet'],
    ];

    public function getDescription(): string
    {
        return 'Point the menu and footer links to pages instead of sections of the home page';
    }

    public function up(Schema $schema): void
    {
        foreach (self::LINKS as [$location, $label, $old, $new]) {
            $this->addSql('UPDATE menu_item SET url = :new WHERE location = :location AND label = :label AND url = :old', ['location' => $location, 'label' => $label, 'old' => $old, 'new' => $new]);
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::LINKS as [$location, $label, $old, $new]) {
            $this->addSql('UPDATE menu_item SET url = :old WHERE location = :location AND label = :label AND url = :new', ['location' => $location, 'label' => $label, 'old' => $old, 'new' => $new]);
        }
    }
}
