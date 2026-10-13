<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013072715 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Optional search settings of posts and pages: title, description, picture, noindex';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page ADD seo_title VARCHAR(70) DEFAULT NULL, ADD seo_description VARCHAR(300) DEFAULT NULL, ADD no_index TINYINT DEFAULT 0 NOT NULL, ADD seo_image_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE page ADD CONSTRAINT FK_140AB620DC02844F FOREIGN KEY (seo_image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_140AB620DC02844F ON page (seo_image_id)');
        $this->addSql('ALTER TABLE post ADD seo_title VARCHAR(70) DEFAULT NULL, ADD seo_description VARCHAR(300) DEFAULT NULL, ADD no_index TINYINT DEFAULT 0 NOT NULL, ADD seo_image_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DDC02844F FOREIGN KEY (seo_image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_5A8A6C8DDC02844F ON post (seo_image_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page DROP FOREIGN KEY FK_140AB620DC02844F');
        $this->addSql('DROP INDEX IDX_140AB620DC02844F ON page');
        $this->addSql('ALTER TABLE page DROP seo_title, DROP seo_description, DROP no_index, DROP seo_image_id');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DDC02844F');
        $this->addSql('DROP INDEX IDX_5A8A6C8DDC02844F ON post');
        $this->addSql('ALTER TABLE post DROP seo_title, DROP seo_description, DROP no_index, DROP seo_image_id');
    }
}
