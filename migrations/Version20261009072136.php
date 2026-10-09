<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Contact form (roadmap step 9b): messages to the club, handled by the committee.
 */
final class Version20261009072136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contact requests; grant CONTACT_MANAGE to the committee';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contact_request (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, subject VARCHAR(20) DEFAULT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, processed_at DATETIME DEFAULT NULL, note LONGTEXT DEFAULT NULL, user_id INT DEFAULT NULL, processed_by_id INT DEFAULT NULL, INDEX IDX_A1B8AE1EA76ED395 (user_id), INDEX IDX_A1B8AE1E2FFD4FD3 (processed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE contact_request ADD CONSTRAINT FK_A1B8AE1EA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE contact_request ADD CONSTRAINT FK_A1B8AE1E2FFD4FD3 FOREIGN KEY (processed_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql(<<<'SQL'
            UPDATE app_group
            SET permissions = JSON_ARRAY_APPEND(permissions, '$', 'CONTACT_MANAGE')
            WHERE code = 'committee' AND NOT JSON_CONTAINS(permissions, '"CONTACT_MANAGE"')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE app_group
            SET permissions = JSON_REMOVE(permissions, JSON_UNQUOTE(JSON_SEARCH(permissions, 'one', 'CONTACT_MANAGE')))
            WHERE code = 'committee' AND JSON_CONTAINS(permissions, '"CONTACT_MANAGE"')
            SQL);
        $this->addSql('ALTER TABLE contact_request DROP FOREIGN KEY FK_A1B8AE1EA76ED395');
        $this->addSql('ALTER TABLE contact_request DROP FOREIGN KEY FK_A1B8AE1E2FFD4FD3');
        $this->addSql('DROP TABLE contact_request');
    }
}
