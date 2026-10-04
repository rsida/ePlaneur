<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Accounts, groups (with the default ones), password reset requests and the Messenger table.
 */
final class Version20261004052618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create accounts, groups, password reset requests and messenger tables; insert default groups';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_group (id INT AUTO_INCREMENT NOT NULL, permissions JSON NOT NULL, description LONGTEXT DEFAULT NULL, all_permissions TINYINT DEFAULT 0 NOT NULL, is_system TINYINT DEFAULT 0 NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_BB13C90877153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, display_name VARCHAR(50) NOT NULL, password VARCHAR(255) NOT NULL, verified TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE app_user_group (user_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_D91914E1A76ED395 (user_id), INDEX IDX_D91914E1FE54D947 (group_id), PRIMARY KEY (user_id, group_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE app_user_group ADD CONSTRAINT FK_D91914E1A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_user_group ADD CONSTRAINT FK_D91914E1FE54D947 FOREIGN KEY (group_id) REFERENCES app_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');

        // Default groups, see App\Security\DefaultGroup
        $this->addSql("INSERT INTO app_group (code, name, description, permissions, all_permissions, is_system) VALUES
            ('member', 'Membre', 'Adhérent à jour de sa cotisation, validé par le club.', '[]', 0, 1),
            ('committee', 'Comité', 'Membre du Comité Directeur.', '[]', 0, 1),
            ('admin', 'Administrateur', 'Administrateur du site : tous les droits.', '[]', 1, 1)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user_group DROP FOREIGN KEY FK_D91914E1A76ED395');
        $this->addSql('ALTER TABLE app_user_group DROP FOREIGN KEY FK_D91914E1FE54D947');
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('DROP TABLE app_group');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE app_user_group');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
