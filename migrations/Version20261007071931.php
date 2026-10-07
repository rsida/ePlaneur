<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * WordPress import (roadmap step 5): origin of imported posts, pages and media, and the redirects of
 * the old addresses.
 */
final class Version20261007071931 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WordPress import: wordpress_id on post and page, source_url on media, redirect table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE redirect (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, source VARCHAR(500) NOT NULL, target VARCHAR(500) NOT NULL, UNIQUE INDEX UNIQ_C30C9E2B5F8A7F73 (source), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE media ADD source_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6A2CA10CA58240EF ON media (source_url)');
        $this->addSql('ALTER TABLE page ADD wordpress_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_140AB620CA7E0ED3 ON page (wordpress_id)');
        $this->addSql('ALTER TABLE post ADD wordpress_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5A8A6C8DCA7E0ED3 ON post (wordpress_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE redirect');
        $this->addSql('DROP INDEX UNIQ_6A2CA10CA58240EF ON media');
        $this->addSql('ALTER TABLE media DROP source_url');
        $this->addSql('DROP INDEX UNIQ_140AB620CA7E0ED3 ON page');
        $this->addSql('ALTER TABLE page DROP wordpress_id');
        $this->addSql('DROP INDEX UNIQ_5A8A6C8DCA7E0ED3 ON post');
        $this->addSql('ALTER TABLE post DROP wordpress_id');
    }
}
