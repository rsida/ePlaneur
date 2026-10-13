<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * "Mon parcours": the steps as the club configures them, their items, the quizzes and what each
 * pilot did.
 */
final class Version20261013202513 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mon parcours: steps, items, quizzes and completions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE path_completion (id INT AUTO_INCREMENT NOT NULL, score INT DEFAULT NULL, attempts INT NOT NULL, done_at DATETIME NOT NULL, pilot_id INT NOT NULL, item_id INT NOT NULL, UNIQUE INDEX UNIQ_A454CD1ACE55439B126F525E (pilot_id, item_id), INDEX IDX_A454CD1ACE55439B (pilot_id), INDEX IDX_A454CD1A126F525E (item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE path_item (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, type VARCHAR(20) NOT NULL, title VARCHAR(120) NOT NULL, text LONGTEXT DEFAULT NULL, url VARCHAR(255) DEFAULT NULL, flight_plan VARCHAR(20) DEFAULT NULL, ranking_from SMALLINT NOT NULL, step_id INT NOT NULL, quiz_id INT DEFAULT NULL, INDEX IDX_7277AC4973B21E9C (step_id), INDEX IDX_7277AC49853CD175 (quiz_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE path_step (id INT AUTO_INCREMENT NOT NULL, step SMALLINT NOT NULL, intro LONGTEXT DEFAULT NULL, open TINYINT NOT NULL, UNIQUE INDEX UNIQ_2ED5776B43B9FE3C (step), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE quiz (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(120) NOT NULL, intro LONGTEXT DEFAULT NULL, pass_mark SMALLINT NOT NULL, questions JSON NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE path_completion ADD CONSTRAINT FK_A454CD1ACE55439B FOREIGN KEY (pilot_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE path_completion ADD CONSTRAINT FK_A454CD1A126F525E FOREIGN KEY (item_id) REFERENCES path_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE path_item ADD CONSTRAINT FK_7277AC4973B21E9C FOREIGN KEY (step_id) REFERENCES path_step (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE path_item ADD CONSTRAINT FK_7277AC49853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE path_completion DROP FOREIGN KEY FK_A454CD1ACE55439B');
        $this->addSql('ALTER TABLE path_completion DROP FOREIGN KEY FK_A454CD1A126F525E');
        $this->addSql('ALTER TABLE path_item DROP FOREIGN KEY FK_7277AC4973B21E9C');
        $this->addSql('ALTER TABLE path_item DROP FOREIGN KEY FK_7277AC49853CD175');
        $this->addSql('DROP TABLE path_completion');
        $this->addSql('DROP TABLE path_item');
        $this->addSql('DROP TABLE path_step');
        $this->addSql('DROP TABLE quiz');
    }
}
