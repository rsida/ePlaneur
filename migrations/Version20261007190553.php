<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Access model: reserved content is announced to the others (listed with a padlock, "Contenu
 * réservé" at its address) or private (listed nowhere, address not found); groups can include other
 * groups (rights and access cumulate).
 *
 * Reserved documents were only listed for their audience: they become private, and so do their
 * files. The committee includes the members.
 */
final class Version20261007190553 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Announced or private reserved content, group inclusions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE group_inclusion (group_id INT NOT NULL, included_group_id INT NOT NULL, INDEX IDX_91A2FF35FE54D947 (group_id), INDEX IDX_91A2FF357489E6C5 (included_group_id), PRIMARY KEY (group_id, included_group_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE group_inclusion ADD CONSTRAINT FK_91A2FF35FE54D947 FOREIGN KEY (group_id) REFERENCES app_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_inclusion ADD CONSTRAINT FK_91A2FF357489E6C5 FOREIGN KEY (included_group_id) REFERENCES app_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document ADD announced TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE media ADD announced TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE page ADD announced TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE post ADD announced TINYINT DEFAULT 1 NOT NULL');
        $this->addSql("UPDATE document SET announced = 0 WHERE visibility <> 'public'");
        $this->addSql('UPDATE media SET announced = 0 WHERE id IN (SELECT file_id FROM document WHERE announced = 0)');
        $this->addSql("INSERT INTO group_inclusion (group_id, included_group_id) SELECT committee.id, member.id FROM app_group committee, app_group member WHERE committee.code = 'committee' AND member.code = 'member'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE group_inclusion DROP FOREIGN KEY FK_91A2FF35FE54D947');
        $this->addSql('ALTER TABLE group_inclusion DROP FOREIGN KEY FK_91A2FF357489E6C5');
        $this->addSql('DROP TABLE group_inclusion');
        $this->addSql('ALTER TABLE document DROP announced');
        $this->addSql('ALTER TABLE media DROP announced');
        $this->addSql('ALTER TABLE page DROP announced');
        $this->addSql('ALTER TABLE post DROP announced');
    }
}
