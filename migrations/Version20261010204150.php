<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The full analysis of a flight moves to flight_data (loaded only when a flight is shown).
 */
final class Version20261010204150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The full analysis of a flight in its own table (flight_data), its statistics and task distance on the flight';
    }

    public function up(Schema $schema): void
    {
        // The analysis of each flight moves to flight_data with the same id; the flight keeps its statistics
        $this->addSql('CREATE TABLE flight_data (id INT AUTO_INCREMENT NOT NULL, analysis JSON NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('INSERT INTO flight_data (id, analysis) SELECT id, analysis FROM flight');
        $this->addSql('ALTER TABLE flight ADD task_distance INT DEFAULT NULL, ADD data_id INT DEFAULT NULL, ADD statistics JSON DEFAULT NULL');
        $this->addSql("UPDATE flight SET data_id = id, statistics = JSON_EXTRACT(analysis, '$.statistics'), task_distance = JSON_EXTRACT(analysis, '$.task.distance')");
        $this->addSql('ALTER TABLE flight MODIFY data_id INT NOT NULL, MODIFY statistics JSON NOT NULL, DROP analysis');
        $this->addSql('ALTER TABLE flight ADD CONSTRAINT FK_C257E60E37F5A13C FOREIGN KEY (data_id) REFERENCES flight_data (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C257E60E37F5A13C ON flight (data_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE flight DROP FOREIGN KEY FK_C257E60E37F5A13C');
        $this->addSql('DROP INDEX UNIQ_C257E60E37F5A13C ON flight');
        $this->addSql('ALTER TABLE flight ADD analysis JSON DEFAULT NULL');
        $this->addSql('UPDATE flight INNER JOIN flight_data ON flight_data.id = flight.data_id SET flight.analysis = flight_data.analysis');
        $this->addSql('ALTER TABLE flight MODIFY analysis JSON NOT NULL, DROP task_distance, DROP data_id, DROP statistics');
        $this->addSql('DROP TABLE flight_data');
    }
}
