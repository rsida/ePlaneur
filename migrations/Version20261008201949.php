<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008201949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flight viewer: comments of pilots and trainers on flights';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE flight_comment (id INT AUTO_INCREMENT NOT NULL, at INT DEFAULT NULL, step INT DEFAULT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, flight_id INT NOT NULL, author_id INT DEFAULT NULL, INDEX IDX_1798B1FA91F478C5 (flight_id), INDEX IDX_1798B1FAF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE flight_comment ADD CONSTRAINT FK_1798B1FA91F478C5 FOREIGN KEY (flight_id) REFERENCES flight (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE flight_comment ADD CONSTRAINT FK_1798B1FAF675F31B FOREIGN KEY (author_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE flight_comment DROP FOREIGN KEY FK_1798B1FA91F478C5');
        $this->addSql('ALTER TABLE flight_comment DROP FOREIGN KEY FK_1798B1FAF675F31B');
        $this->addSql('DROP TABLE flight_comment');
    }
}
