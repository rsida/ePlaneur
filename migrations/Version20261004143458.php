<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Posts with content blocks, categories, media library, author profile; default post permissions
 * for the committee group.
 */
final class Version20261004143458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create posts, categories and media; add author profile fields; grant post permissions to the committee';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, description LONGTEXT DEFAULT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, UNIQUE INDEX UNIQ_64C19C1989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE media (id INT AUTO_INCREMENT NOT NULL, alt VARCHAR(255) DEFAULT NULL, credit VARCHAR(255) DEFAULT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, pages INT DEFAULT NULL, visibility VARCHAR(20) DEFAULT \'public\' NOT NULL, uploaded_at DATETIME NOT NULL, path VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, UNIQUE INDEX UNIQ_6A2CA10CB548B0F (path), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE media_allowed_group (media_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_4A0660BCEA9FDD75 (media_id), INDEX IDX_4A0660BCFE54D947 (group_id), PRIMARY KEY (media_id, group_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post (id INT AUTO_INCREMENT NOT NULL, kicker VARCHAR(120) DEFAULT NULL, badge VARCHAR(40) DEFAULT NULL, excerpt LONGTEXT NOT NULL, highlight LONGTEXT DEFAULT NULL, highlight_note LONGTEXT DEFAULT NULL, cover_caption VARCHAR(255) DEFAULT NULL, keywords JSON NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, featured TINYINT DEFAULT 0 NOT NULL, visibility VARCHAR(20) DEFAULT \'public\' NOT NULL, body JSON NOT NULL, aside JSON NOT NULL, outro JSON NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(160) NOT NULL, cover_id INT DEFAULT NULL, category_id INT DEFAULT NULL, author_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_5A8A6C8D989D9B62 (slug), INDEX post_published_at_idx (published_at), INDEX IDX_5A8A6C8D922726E9 (cover_id), INDEX IDX_5A8A6C8D12469DE2 (category_id), INDEX IDX_5A8A6C8DF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post_allowed_group (post_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_B110E00E4B89032C (post_id), INDEX IDX_B110E00EFE54D947 (group_id), PRIMARY KEY (post_id, group_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE media_allowed_group ADD CONSTRAINT FK_4A0660BCEA9FDD75 FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media_allowed_group ADD CONSTRAINT FK_4A0660BCFE54D947 FOREIGN KEY (group_id) REFERENCES app_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8D922726E9 FOREIGN KEY (cover_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8D12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DF675F31B FOREIGN KEY (author_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE post_allowed_group ADD CONSTRAINT FK_B110E00E4B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_allowed_group ADD CONSTRAINT FK_B110E00EFE54D947 FOREIGN KEY (group_id) REFERENCES app_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_user ADD job_title VARCHAR(100) DEFAULT NULL, ADD bio LONGTEXT DEFAULT NULL, ADD avatar_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD CONSTRAINT FK_88BDF3E986383B10 FOREIGN KEY (avatar_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_88BDF3E986383B10 ON app_user (avatar_id)');

        // The committee writes and publishes the news (administrators can change it afterwards)
        $this->addSql(<<<'SQL'
            UPDATE app_group
            SET permissions = '["POST_CREATE","POST_EDIT","POST_PUBLISH","CATEGORY_MANAGE","MEDIA_MANAGE"]'
            WHERE code = 'committee' AND permissions = '[]'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_allowed_group DROP FOREIGN KEY FK_4A0660BCEA9FDD75');
        $this->addSql('ALTER TABLE media_allowed_group DROP FOREIGN KEY FK_4A0660BCFE54D947');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8D922726E9');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8D12469DE2');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DF675F31B');
        $this->addSql('ALTER TABLE post_allowed_group DROP FOREIGN KEY FK_B110E00E4B89032C');
        $this->addSql('ALTER TABLE post_allowed_group DROP FOREIGN KEY FK_B110E00EFE54D947');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE media');
        $this->addSql('DROP TABLE media_allowed_group');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE post_allowed_group');
        $this->addSql('ALTER TABLE app_user DROP FOREIGN KEY FK_88BDF3E986383B10');
        $this->addSql('DROP INDEX IDX_88BDF3E986383B10 ON app_user');
        $this->addSql('ALTER TABLE app_user DROP job_title, DROP bio, DROP avatar_id');
    }
}
