<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Pages, navigation menus and official documents; initial menus (current header and footer links);
 * page and document permissions for the committee.
 */
final class Version20261004155717 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pages, menu items and official documents; seed the main and footer menus; grant page and document permissions to the committee';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, description LONGTEXT DEFAULT NULL, version VARCHAR(30) DEFAULT NULL, details JSON NOT NULL, position INT DEFAULT 0 NOT NULL, updated_at DATETIME NOT NULL, visibility VARCHAR(20) DEFAULT \'public\' NOT NULL, title VARCHAR(255) NOT NULL, category_id INT DEFAULT NULL, file_id INT NOT NULL, INDEX IDX_D8698A7612469DE2 (category_id), INDEX IDX_D8698A7693CB796C (file_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document_allowed_group (document_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_89CEFA96C33F7837 (document_id), INDEX IDX_89CEFA96FE54D947 (group_id), PRIMARY KEY (document_id, group_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document_category (id INT AUTO_INCREMENT NOT NULL, position INT DEFAULT 0 NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, UNIQUE INDEX UNIQ_898DE898989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE menu_item (id INT AUTO_INCREMENT NOT NULL, position INT DEFAULT 0 NOT NULL, url VARCHAR(255) DEFAULT NULL, description VARCHAR(100) DEFAULT NULL, visibility VARCHAR(20) DEFAULT \'public\' NOT NULL, location VARCHAR(20) NOT NULL, label VARCHAR(100) NOT NULL, parent_id INT DEFAULT NULL, page_id INT DEFAULT NULL, INDEX menu_item_location_idx (location), INDEX IDX_D754D550727ACA70 (parent_id), INDEX IDX_D754D550C4663E4 (page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE menu_item_allowed_group (menu_item_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_8BECE8909AB44FE0 (menu_item_id), INDEX IDX_8BECE890FE54D947 (group_id), PRIMARY KEY (menu_item_id, group_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE page (id INT AUTO_INCREMENT NOT NULL, position INT DEFAULT 0 NOT NULL, kicker VARCHAR(120) DEFAULT NULL, excerpt LONGTEXT DEFAULT NULL, highlight LONGTEXT DEFAULT NULL, highlight_note LONGTEXT DEFAULT NULL, link_label VARCHAR(100) DEFAULT NULL, published_at DATETIME DEFAULT NULL, updated_at DATETIME NOT NULL, visibility VARCHAR(20) DEFAULT \'public\' NOT NULL, body JSON NOT NULL, aside JSON NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(120) NOT NULL, parent_id INT DEFAULT NULL, UNIQUE INDEX page_parent_slug (parent_id, slug), INDEX IDX_140AB620727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE page_allowed_group (page_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_3FBB2E26C4663E4 (page_id), INDEX IDX_3FBB2E26FE54D947 (group_id), PRIMARY KEY (page_id, group_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A7612469DE2 FOREIGN KEY (category_id) REFERENCES document_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A7693CB796C FOREIGN KEY (file_id) REFERENCES media (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE document_allowed_group ADD CONSTRAINT FK_89CEFA96C33F7837 FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_allowed_group ADD CONSTRAINT FK_89CEFA96FE54D947 FOREIGN KEY (group_id) REFERENCES app_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550727ACA70 FOREIGN KEY (parent_id) REFERENCES menu_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550C4663E4 FOREIGN KEY (page_id) REFERENCES page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_item_allowed_group ADD CONSTRAINT FK_8BECE8909AB44FE0 FOREIGN KEY (menu_item_id) REFERENCES menu_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_item_allowed_group ADD CONSTRAINT FK_8BECE890FE54D947 FOREIGN KEY (group_id) REFERENCES app_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page ADD CONSTRAINT FK_140AB620727ACA70 FOREIGN KEY (parent_id) REFERENCES page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page_allowed_group ADD CONSTRAINT FK_3FBB2E26C4663E4 FOREIGN KEY (page_id) REFERENCES page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page_allowed_group ADD CONSTRAINT FK_3FBB2E26FE54D947 FOREIGN KEY (group_id) REFERENCES app_group (id) ON DELETE CASCADE');

        // Initial menus: the links the header and footer had in code, so a new installation is
        // navigable; administrators edit them afterwards (MenuItem)
        $position = 0;
        foreach (['Accueil' => '/', 'Formation' => '/#formation', 'Voler' => '/#vols', 'Le Club' => '/#club', 'Actualités' => '/actualites'] as $label => $url) {
            $this->addSql('INSERT INTO menu_item (location, label, url, position, visibility) VALUES (?, ?, ?, ?, ?)', ['main', $label, $url, $position++, 'public']);
        }

        $columns = [
            'Formation' => ['Installer Condor 2' => '/#guides', 'Installer Condor 3' => '/#guides', 'Parcours guidé' => '/#formation', 'Vidéos pédagogiques' => '/#formation', 'Bonnes pratiques' => '/#guides'],
            'Voler' => ['Vols en réseau' => '/#vols', 'Participer à un vol' => '/#vols', 'Prérequis pour voler' => '/#vols', 'Déposer une trace' => '/#carnet', 'Créer mon carnet' => '/#carnet'],
            'Le Club' => ['Présentation du club' => '/#club', 'Liste des membres' => '/#communaute', 'Actualités' => '/actualites', 'Rejoindre le club' => '/#rejoindre', 'Contacter le club' => '/#rejoindre'],
        ];
        $column = 0;
        foreach ($columns as $title => $links) {
            $this->addSql('INSERT INTO menu_item (location, label, position, visibility) VALUES (?, ?, ?, ?)', ['footer', $title, $column++, 'public']);
            $this->addSql('SET @column = LAST_INSERT_ID()');
            $position = 0;
            foreach ($links as $label => $url) {
                $this->addSql('INSERT INTO menu_item (location, parent_id, label, url, position, visibility) VALUES (?, @column, ?, ?, ?, ?)', ['footer', $label, $url, $position++, 'public']);
            }
        }

        // The committee manages pages and official documents (administrators can change it)
        $this->addSql(<<<'SQL'
            UPDATE app_group
            SET permissions = JSON_ARRAY_APPEND(permissions, '$', 'PAGE_MANAGE', '$', 'DOCUMENT_MANAGE')
            WHERE code = 'committee' AND NOT JSON_CONTAINS(permissions, '"PAGE_MANAGE"')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A7612469DE2');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A7693CB796C');
        $this->addSql('ALTER TABLE document_allowed_group DROP FOREIGN KEY FK_89CEFA96C33F7837');
        $this->addSql('ALTER TABLE document_allowed_group DROP FOREIGN KEY FK_89CEFA96FE54D947');
        $this->addSql('ALTER TABLE menu_item DROP FOREIGN KEY FK_D754D550727ACA70');
        $this->addSql('ALTER TABLE menu_item DROP FOREIGN KEY FK_D754D550C4663E4');
        $this->addSql('ALTER TABLE menu_item_allowed_group DROP FOREIGN KEY FK_8BECE8909AB44FE0');
        $this->addSql('ALTER TABLE menu_item_allowed_group DROP FOREIGN KEY FK_8BECE890FE54D947');
        $this->addSql('ALTER TABLE page DROP FOREIGN KEY FK_140AB620727ACA70');
        $this->addSql('ALTER TABLE page_allowed_group DROP FOREIGN KEY FK_3FBB2E26C4663E4');
        $this->addSql('ALTER TABLE page_allowed_group DROP FOREIGN KEY FK_3FBB2E26FE54D947');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE document_allowed_group');
        $this->addSql('DROP TABLE document_category');
        $this->addSql('DROP TABLE menu_item');
        $this->addSql('DROP TABLE menu_item_allowed_group');
        $this->addSql('DROP TABLE page');
        $this->addSql('DROP TABLE page_allowed_group');
    }
}
