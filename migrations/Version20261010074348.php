<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261010074348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messages between members (9c), and MESSAGE_SEND for the members';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE conversation (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(160) NOT NULL, created_at DATETIME NOT NULL, last_message_at DATETIME NOT NULL, addressed_group_id INT DEFAULT NULL, flight_id INT DEFAULT NULL, INDEX IDX_8A8E26E96F60E3AF (last_message_at), INDEX IDX_8A8E26E9BF43DFF3 (addressed_group_id), INDEX IDX_8A8E26E991F478C5 (flight_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conversation_participant (id INT AUTO_INCREMENT NOT NULL, read_at DATETIME DEFAULT NULL, conversation_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_398016619AC0396A76ED395 (conversation_id, user_id), INDEX IDX_398016619AC0396 (conversation_id), INDEX IDX_39801661A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, conversation_id INT NOT NULL, author_id INT DEFAULT NULL, INDEX IDX_B6BD307F9AC0396 (conversation_id), INDEX IDX_B6BD307FF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9BF43DFF3 FOREIGN KEY (addressed_group_id) REFERENCES app_group (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E991F478C5 FOREIGN KEY (flight_id) REFERENCES flight (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_398016619AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_39801661A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF675F31B FOREIGN KEY (author_id) REFERENCES app_user (id) ON DELETE SET NULL');
        // Members write messages; the committee and the administrators through the groups they include
        $this->addSql(<<<'SQL'
            UPDATE app_group
            SET permissions = JSON_ARRAY_APPEND(permissions, '$', 'MESSAGE_SEND')
            WHERE code = 'member' AND NOT JSON_CONTAINS(permissions, '"MESSAGE_SEND"')
            SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            UPDATE app_group
            SET permissions = JSON_REMOVE(permissions, JSON_UNQUOTE(JSON_SEARCH(permissions, 'one', 'MESSAGE_SEND')))
            WHERE code = 'member' AND JSON_CONTAINS(permissions, '"MESSAGE_SEND"')
            SQL);
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9BF43DFF3');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E991F478C5');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_398016619AC0396');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_39801661A76ED395');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F9AC0396');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF675F31B');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE conversation_participant');
        $this->addSql('DROP TABLE message');
    }
}
