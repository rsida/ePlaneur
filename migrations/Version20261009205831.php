<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Follow-up of the training path (roadmap step 9g): where each pilot stands on each step.
 */
final class Version20261009205831 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Progress of the pilots on the steps of the training path';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE step_progress (id INT AUTO_INCREMENT NOT NULL, step SMALLINT NOT NULL, status VARCHAR(20) NOT NULL, note LONGTEXT DEFAULT NULL, validated_on DATE DEFAULT NULL, updated_at DATETIME NOT NULL, pilot_id INT NOT NULL, flight_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_1204F029CE55439B43B9FE3C (pilot_id, step), INDEX IDX_1204F029CE55439B (pilot_id), INDEX IDX_1204F02991F478C5 (flight_id), INDEX IDX_1204F029896DBBDE (updated_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE step_progress ADD CONSTRAINT FK_1204F029CE55439B FOREIGN KEY (pilot_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE step_progress ADD CONSTRAINT FK_1204F02991F478C5 FOREIGN KEY (flight_id) REFERENCES flight (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE step_progress ADD CONSTRAINT FK_1204F029896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE step_progress DROP FOREIGN KEY FK_1204F029CE55439B');
        $this->addSql('ALTER TABLE step_progress DROP FOREIGN KEY FK_1204F02991F478C5');
        $this->addSql('ALTER TABLE step_progress DROP FOREIGN KEY FK_1204F029896DBBDE');
        $this->addSql('DROP TABLE step_progress');
    }
}
