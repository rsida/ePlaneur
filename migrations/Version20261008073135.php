<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008073135 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flight viewer: uploaded flight tracks and their analysis';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE flight (id INT AUTO_INCREMENT NOT NULL, pilot VARCHAR(100) NOT NULL, competition_id VARCHAR(10) DEFAULT NULL, glider VARCHAR(60) DEFAULT NULL, landscape VARCHAR(60) DEFAULT NULL, simulator VARCHAR(30) NOT NULL, format VARCHAR(5) NOT NULL, race_id BIGINT DEFAULT NULL, flight_plan VARCHAR(160) DEFAULT NULL, flown_on DATE DEFAULT NULL, duration INT NOT NULL, distance INT NOT NULL, task_speed DOUBLE PRECISION DEFAULT NULL, analysis JSON NOT NULL, file_name VARCHAR(80) NOT NULL, original_name VARCHAR(255) NOT NULL, uploaded_at DATETIME NOT NULL, owner_id INT DEFAULT NULL, INDEX IDX_C257E60E6E59D40D (race_id), INDEX IDX_C257E60E7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE flight ADD CONSTRAINT FK_C257E60E7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE flight DROP FOREIGN KEY FK_C257E60E7E3C61F9');
        $this->addSql('DROP TABLE flight');
    }
}
